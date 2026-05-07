<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\TrackingLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class OrderController extends Controller
{
    // --- KONFIGURASI HARGA & SATUAN ---
    private $daftarHarga = [
        'Cuci Lipat'        => 5000,
        'Setrika'           => 5000,
        'Cuci Setrika'      => 5000,
        'Express 1 Hari'    => 12000,
        'Cuci Basah'        => 4000,
        'Bed Cover Besar'   => 35000,
        'Bed Cover Kecil'   => 20000,
        'Sprei'             => 15000,
        'Sprei Aja'         => 10000,
        'Sprei Single'      => 10000,
        'Karpet'            => 20000,
        'Gorden'            => 15000,
    ];

    // =========================================================================
    // FUNGSI HELPER: KIRIM WA FONNTE
    // =========================================================================
    private function sendWhatsApp($target, $message, $fileUrl = null)
{
    // 1. BERSIHKAN NOMOR: Buang spasi, tanda +, strip, dll. Tinggalkan hanya angka.
    $target = preg_replace('/[^0-9]/', '', $target);

    // ⚠️ Token Fonnte Kamu
    $token = 'V98JKnEB5gMHA2AnoQ3j'; 

    $postData = [
        'target' => $target,
        'message' => $message,
        'countryCode' => '62', // Fonnte akan otomatis mengubah 08.. jadi 628..
    ];

    if ($fileUrl) {
        $postData['url'] = $fileUrl;
    }

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.fonnte.com/send',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        // 2. TIMEOUT DIPERSINGKAT: Maksimal 5 detik! Kalau server Fonnte lemot, tinggalkan.
        CURLOPT_TIMEOUT => 5, 
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_HTTPHEADER => array(
            "Authorization: $token"
        ),
    ));

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    
    // 3. LOGGING: Cek file storage/logs/laravel.log untuk melihat alasan error-nya
    if ($err) {
        \Log::error("Fonnte Error ke $target : " . $err);
    } else {
        \Log::info("Fonnte Respons ke $target : " . $response);
    }

    return $response;
}
    // =========================================================================

    public function index()
    {
        try {
            // 1. Tambahkan with('courier') agar relasi kurirnya ikut ditarik dari database
            $orders = Order::with('courier')->orderBy('created_at', 'desc')->get();
            
            // 2. Format datanya agar struktur JSON-nya cocok dengan Flutter
            $formattedOrders = $orders->map(function ($order) {
                $orderData = $order->toArray();
                
                // Jika kurir sudah ditugaskan, masukkan namanya ke JSON
                if ($order->courier) {
                    $orderData['courier_name'] = $order->courier->name;
                    $orderData['courier_phone'] = $order->courier->phone ?? '-'; 
                    $orderData['courier_image'] = $order->courier->photo_url; 
                } else {
                    $orderData['courier_name'] = null;
                    $orderData['courier_phone'] = null;
                    $orderData['courier_image'] = null;
                }
                
                return $orderData;
            });

            return response()->json(['message' => 'Daftar semua pesanan', 'data' => $formattedOrders], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal mengambil data', 'error' => $e->getMessage()], 500);
        }
    }

   // Jangan lupa pastikan 2 baris ini ada di paling atas file (di bawah namespace):
    // use Illuminate\Support\Facades\Storage;
    // use SimpleSoftwareIO\QrCode\Facades\QrCode;

   public function store(Request $request)
    {
        try {
            $request->validate([
                'customer_name' => 'required',
                'wa_number'     => 'required',
                'address'       => 'required',
                'service'       => 'required',
                'weight'        => 'nullable|numeric', 
            ]);

            $orderCode = 'LDR-' . strtoupper(Str::random(6));
            $layanan = $request->service;
            $berat = $request->weight ?? 0; 
            
            $hargaSatuan = $this->daftarHarga[$layanan] ?? 0;
            $totalHarga = $hargaSatuan * $berat;

            $order = Order::create([
                'order_code'    => $orderCode,
                'customer_name' => $request->customer_name,
                'wa_number'     => $request->wa_number,
                'address'       => $request->address,
                'weight'        => $berat,
                'service'       => $layanan,
                'total_price'   => $totalHarga,
                'status'        => 'Menunggu Pembayaran',
                'customer_lat'  => $request->lat, 
                'customer_lng'  => $request->lng,
            ]);

            TrackingLog::create([
                'order_id' => $order->id, 
                'status'   => 'Pesanan Dibuat',
                'message'  => "Pesanan {$layanan} diterima. Menunggu proses timbang.",
            ]);

           $fileName = $orderCode . '.svg';
            $qrData = QrCode::size(300)->margin(1)->generate($orderCode);
            Storage::disk('public')->put('qrcodes/' . $fileName, $qrData);
            $qrUrl = asset('storage/qrcodes/' . $fileName); 
            
            // 1. Notif Pelanggan
            $pesanPelanggan = "Halo *{$request->customer_name}*! 👋\n\nTerima kasih telah mempercayakan cucian Anda di *Lyra Laundry*.\n\n🧾 *Kode:* {$orderCode}\n📦 *Layanan:* {$layanan}\n\nSimpan QR ini untuk cek status pesanan.";
            $this->sendWhatsApp($request->wa_number, $pesanPelanggan, $qrUrl);

            // 2. Notif Semua Kurir (AMBIL DARI DATABASE)
            $daftarKurir = \App\Models\User::where('role', 'courier')->whereNotNull('phone')->get();
            $pesanTugas = "🚨 *TUGAS JEMPUT BARU* 🚨\n\n👤 *Nama:* {$request->customer_name}\n📍 *Alamat:* {$request->address}\n🧾 *Kode:* {$orderCode}";
            
            foreach ($daftarKurir as $kurir) {
                $this->sendWhatsApp($kurir->phone, $pesanTugas);
            }

            return response()->json(['message' => 'Order Created', 'data' => $order], 201);
            
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function updateWeight(Request $request, $id)
    {
        $request->validate(['weight' => 'required|numeric']);
        $order = Order::findOrFail($id);
        
        $hargaSatuan = $this->daftarHarga[$order->service] ?? 0;
        $totalHarga = $hargaSatuan * $request->weight;

        $order->update(['weight' => $request->weight, 'total_price' => $totalHarga]);

        return response()->json(['status' => 'success', 'message' => 'Berat & Harga diupdate', 'data' => $order]);
    }

    public function updateImage(Request $request, $id)
    {
        try {
            $request->validate(['image' => 'required|image|mimes:jpeg,png,jpg|max:10240']);
            $order = Order::findOrFail($id);
            $imagePath = $request->file('image')->store('orders', 'public');
            $order->update(['image_path' => $imagePath]);

            return response()->json(['message' => 'Foto diupload', 'image_url' => asset('storage/' . $imagePath)], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        try {
            $request->validate(['status' => 'required|string']);
            $order = Order::findOrFail($id);
            $order->update(['status' => $request->status]);

            TrackingLog::create([
                'order_id' => $order->id,
                'status'   => $request->status,
                'message'  => 'Status cucian diperbarui menjadi: ' . $request->status,
            ]);

            // --- 📱 ALUR WA 3: KIRIM UPDATE TIAP KALI STATUS BERUBAH ---
            $status = $request->status;
            $pesanStatus = "Halo *{$order->customer_name}*,\n\nPemberitahuan dari *Lyra Laundry*! 📢\nStatus pesanan cucian Anda (*{$order->order_code}*) saat ini telah berubah menjadi:\n\n✨ *[ {$status} ]* ✨\n\n";

            // Kustomisasi pesan sesuai statusnya biar nggak bosenin
            if ($status == 'Menunggu Pembayaran') {
                $pesanStatus .= "Silakan lakukan pembayaran melalui aplikasi agar pesanan Anda dapat segera kami proses.";
            } elseif ($status == 'Lunas - Siap Jemput') {
                $pesanStatus .= "Pembayaran berhasil! Kurir kami akan segera meluncur ke lokasi Anda. Mohon siapkan cuciannya ya.";
            } elseif ($status == 'Proses Cuci') {
                $pesanStatus .= "Pakaian Anda sedang kami sulap jadi bersih dan wangi! Tunggu update selanjutnya ya.";
            } elseif ($status == 'Proses Antar') {
                $pesanStatus .= "Cucian Anda sudah rapi dan sedang dalam perjalanan oleh kurir kami. Siap-siap di lokasi ya!";
            } elseif ($status == 'Selesai') {
                $pesanStatus .= "Hore! Cucian Anda sudah selesai dan rapi. Terima kasih telah menggunakan jasa kami! 🙏";
            } else {
                $pesanStatus .= "Terima kasih telah mempercayakan cucian Anda kepada kami.";
            }

            $this->sendWhatsApp($order->wa_number, $pesanStatus);

            return response()->json(['message' => 'Status berhasil diupdate', 'data' => $order], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function financialReport()
    {
        $baseQuery = Order::where('status', '!=', 'Menunggu Pembayaran');

        $dailyOrders = (clone $baseQuery)->whereDate('created_at', Carbon::today())->get();
        $dailyBreakdown = $dailyOrders->map(fn($o) => ['label' => $o->customer_name . ' (' . Carbon::parse($o->created_at)->format('H:i') . ')', 'revenue' => (float) $o->total_price, 'orders' => 1]);

        $weeklyOrders = (clone $baseQuery)->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])->get();
        $weeklyBreakdown = $weeklyOrders->groupBy(fn($o) => Carbon::parse($o->created_at)->translatedFormat('l, d M'))
            ->map(fn($group, $day) => ['label' => $day, 'revenue' => (float)$group->sum('total_price'), 'orders' => $group->count()])->values();

        $monthlyOrders = (clone $baseQuery)->whereMonth('created_at', Carbon::now()->month)->whereYear('created_at', Carbon::now()->year)->get();
        $monthlyBreakdown = $monthlyOrders->groupBy(fn($o) => Carbon::parse($o->created_at)->translatedFormat('d M Y'))
            ->map(fn($group, $date) => ['label' => $date, 'revenue' => (float)$group->sum('total_price'), 'orders' => $group->count()])->values();

        return response()->json([
            'data' => [
                'daily' => ['total_revenue' => (float)$dailyOrders->sum('total_price'), 'total_orders' => $dailyOrders->count(), 'breakdown' => $dailyBreakdown],
                'weekly' => ['total_revenue' => (float)$weeklyOrders->sum('total_price'), 'total_orders' => $weeklyOrders->count(), 'breakdown' => $weeklyBreakdown],
                'monthly' => ['total_revenue' => (float)$monthlyOrders->sum('total_price'), 'total_orders' => $monthlyOrders->count(), 'breakdown' => $monthlyBreakdown],
                'yearly' => (float)(clone $baseQuery)->whereYear('created_at', Carbon::now()->year)->sum('total_price'),
                'completed_count' => Order::where('status', 'Selesai')->count(),
                'process_count' => Order::where('status', '!=', 'Selesai')->where('status', '!=', 'Menunggu Pembayaran')->count(),
            ]
        ]);
    }

   public function show($code) {
    // 1. Ambil order beserta relasi logs dan courier (kurir)
    // Asumsi: di model Order.php kamu sudah ada relasi public function courier()
    $order = Order::with(['logs', 'courier'])->where('order_code', $code)->first();
    
    if (!$order) {
        return response()->json(['message' => 'Not Found'], 404);
    }

    // 2. Format response agar sesuai dengan yang diminta Flutter
    $orderData = $order->toArray();
    
    // 3. Sisipkan data kurir ke dalam response JSON
    if ($order->courier) {
        $orderData['courier_name'] = $order->courier->name;
        
        // Cek apakah tabel users punya kolom phone/no_hp. Jika tidak ada, kita kosongkan dulu.
        $orderData['courier_phone'] = $order->courier->phone ?? '-'; 
        
        // Menggunakan getPhotoUrlAttribute() yang sudah kamu buat di model User
        $orderData['courier_image'] = $order->courier->photo_url; 
    } else {
        // Jika belum ada kurir yang di-assign
        $orderData['courier_name'] = null;
        $orderData['courier_phone'] = null;
        $orderData['courier_image'] = null;
    }

    return response()->json(['data' => $orderData], 200);
}

        public function assignCourier(Request $request, $id)
        {
            // 1. Validasi input: pastikan courier_id dikirim dan ada di tabel users
            $request->validate([
                'courier_id' => 'required|exists:users,id'
            ]);

            // 2. Cari data pesanan berdasarkan ID
            $order = Order::findOrFail($id);
            
            // 3. Update courier_id di database
            $order->courier_id = $request->courier_id;
            $order->save();

            // 4. Catat ke riwayat pelacakan
            TrackingLog::create([
                'order_id' => $order->id,
                'status'   => 'Kurir Ditugaskan',
                'message'  => 'Kurir telah ditugaskan dan akan segera memproses pesanan Anda.',
            ]);

            // 5. Ambil data kurir untuk mendapatkan nomor teleponnya
            $courier = \App\Models\User::find($request->courier_id);
            
            // 6. Cek apakah kurir ditemukan dan memiliki nomor telepon (kolom 'phone')
            if ($courier && !empty($courier->phone)) { 
                $pesanKurir = "🚨 *TUGAS BARU* 🚨\n\n" .
                            "Halo *{$courier->name}*, kamu ditugaskan untuk pesanan:\n" .
                            "📦 Kode: *{$order->order_code}*\n" .
                            "📍 Alamat: {$order->address}\n" .
                            "👤 Pelanggan: {$order->customer_name}\n\n" .
                            "Silakan cek aplikasi untuk detailnya. Semangat! 🚀";

                // Kirim pesan ke nomor kurir yang ada di database
                $this->sendWhatsApp($courier->phone, $pesanKurir);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Kurir berhasil ditugaskan dan notifikasi dikirim',
                'data' => $order
            ], 200);
        }

    public function updateCourierLocation(Request $request, $order_code) {
        $order = Order::where('order_code', $order_code)->first();
        if (!$order) return response()->json(['success' => false, 'message' => 'Pesanan tidak ditemukan'], 404);

        $request->validate(['lat' => 'required|numeric', 'lng' => 'required|numeric']);
        $order->update(['courier_lat' => $request->lat, 'courier_lng' => $request->lng]);

        return response()->json(['success' => true, 'message' => 'Lokasi kurir diperbarui', 'data' => ['courier_lat' => $order->courier_lat, 'courier_lng' => $order->courier_lng]], 200);
    }

    public function updateLocation(Request $request, $id) {
        $order = Order::findOrFail($id);
        $order->update(['courier_lat' => $request->lat, 'courier_lng' => $request->lng, 'status' => 'Sedang Diantar']);
        return response()->json(['message' => 'Location Updated'], 200);
    }

    public function payment(Request $request, $id) {
        $order = Order::findOrFail($id);
        $order->update(['status' => 'Sudah Dibayar']);
        TrackingLog::create(['order_id' => $order->id, 'status' => 'Pembayaran Berhasil', 'message' => 'Pesanan lunas.']);
        return response()->json(['message' => 'Payment Success'], 200);
    }
}