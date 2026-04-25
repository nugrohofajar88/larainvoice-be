<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

use App\Models\PlateVariant;
use PhpParser\Node\Scalar\String_;

class MigrateLegacyData extends Command
{
    protected $signature = 'app:migrate-legacy-data';
    protected $description = 'Migrasi data dari db_cnc (lama) ke pioneer_cnc (baru)';

    public function handle()
    {
        $this->info('--- Memulai Proses Migrasi ---');

        // Matikan proteksi foreign key sementara
        Schema::disableForeignKeyConstraints();

        // 1. TAHAP MASTER (Independen)
        $this->migrateBranches();
        $this->migratePlateTypes();
        $this->migrateSizes();
        $this->migrateMachineTypes();

        // 2. TAHAP ENTITAS (Bergantung pada master)
        $this->migrateUsers();
        $this->migrateSales();
        $this->migrateCustomers();

        // 3. TAHAP RELASI KOMPLEKS (Mapping)
        $this->migratePlateVariants();
        $this->migrateCuttingPrices();

        // 4. TAHAP TRANSAKSI (History)
        $this->migrateInvoices();
        $this->migratePayments();

        Schema::enableForeignKeyConstraints();

        $this->info('--- Semua Proses Migrasi Selesai ---');
    }

    private function migrateBranches()
    {

        $this->info('Migrating: Branches...');

        DB::table('branches')->truncate();

        $old = DB::connection('mysql_old')->table('offices')->get();
        foreach ($old as $item) {
            DB::table('branches')->insert(
                [
                    'id' => $item->id,
                    'name' => $item->name,
                    'city' => $item->city,
                    'address' => $item->address,
                    'phone' => $item->phone,
                    'email' => $item->email,
                    'website' => $item->website,
                    'created_at' => $item->created_on,
                    'updated_at' => now(),
                    'deleted_at' => $item->deleted ? now() : null,
                ]
            );
        }

        // Reset Auto Increment ke ID terbesar + 1
        $maxId = DB::table('branches')->max('id');
        if ($maxId) {
            DB::statement("ALTER TABLE branches AUTO_INCREMENT = " . ($maxId + 1));
        }

        DB::table('branch_invoice_counters')->truncate();
        $old = DB::connection('mysql_old')->table('office_format_files')->get();
        foreach ($old as $item) {
            $office = DB::table('branches')->where('id', $item->id_office)->first();
            if ($office) {
                DB::table('branch_invoice_counters')->insert(
                    [
                        'branch_id' => $item->id_office,
                        'prefix' => $item->prefix,
                        'month' => $item->month_file,
                        'year' => $item->year_file,
                        'last_number' => $item->no_file,
                        'created_at' => now(),
                        'updated_at' => null,
                    ]
                );
            }
        }

        DB::table('branch_settings')->truncate();
        $old = DB::connection('mysql_old')->table('office_settings')->get();
        foreach ($old as $item) {
            $office = DB::table('branches')->where('id', $item->id_office)->first();
            if ($office) {
                $branchSetting = DB::table('branch_settings')->where('branch_id', $item->id_office)->first();

                $minimum_stock = 0;
                $sales_commission_percentage = 0;
                $invoice_header_name = null;
                $invoice_header_position = null;
                $invoice_footer_note = "Terima kasih atas kepercayaan Anda. Kami berharap dapat terus melayani kebutuhan Anda dengan baik.";

                switch ($item->code) {
                    case "MIN_STOCK":
                        $minimum_stock = $item->value;
                        break;
                    case "SALES_COMPENSATION_PERCENTAGE":
                        $sales_commission_percentage = $item->value;
                        break;
                    case "INVOICE_PERSON_NAME":
                        $invoice_header_name = $item->value;
                        break;
                    case "INVOICE_PERSON_POSITION":
                        $invoice_header_position = $item->value;
                        break;
                    default:
                }

                if (!$branchSetting) {
                    DB::table('branch_settings')->insert(
                        [
                            'branch_id' => $item->id_office,
                            'minimum_stock' => $minimum_stock,
                            'sales_commission_percentage' => $sales_commission_percentage,
                            'invoice_header_name' => $invoice_header_name,
                            'invoice_header_position' => $invoice_header_position,
                            'invoice_footer_note' => $invoice_footer_note,
                            'created_at' => now(),
                            'updated_at' => null,
                        ]
                    );
                } else {
                    $data = [];
                    switch ($item->code) {
                        case "MIN_STOCK":
                            $data['minimum_stock'] = $item->value;
                            break;
                        case "SALES_COMPENSATION_PERCENTAGE":
                            $data['sales_commission_percentage'] = $item->value;
                            break;
                        case "INVOICE_PERSON_NAME":
                            $data['invoice_header_name'] = $item->value;
                            break;
                        case "INVOICE_PERSON_POSITION":
                            $data['invoice_header_position'] = $item->value;
                            break;
                        default:
                    }

                    DB::table('branch_settings')->where('branch_id', $item->id_office)->update($data);
                }
            } else {
                $this->warn("Branch with ID {$item->id_office} not found for settings code: {$item->code}");
            }
        }
    }

    private function migrateUsers()
    {
        $this->info('Migrating: Users...');

        // Hapus semua user kecuali yang bernama 'Developer' untuk mencegah konflik data.
        DB::table('users')->where('name', '<>', 'Developer')->delete();

        $old = DB::connection('mysql_old')
            ->table('users as u')
            ->select('u.*', 'ug.group_id')
            ->leftJoin('users_groups as ug', 'u.id', '=', 'ug.user_id')
            ->get();

        foreach ($old as $item) {
            // Mapping role dari table pivot lama
            $oldRole = DB::connection('mysql_old')->table('users_groups')
                ->where('user_id', $item->id)->first();

            DB::table('users')->updateOrInsert(
                ['id' => $item->id],
                [
                    'name' => $item->first_name . ' ' . $item->last_name,
                    'email' => empty($item->email) ? "{$item->username}@example.com" : $item->email,
                    'username' => $item->username,
                    'password' => $item->password, // Note: Cek kompatibilitas hash
                    'role_id' => $item->group_id,
                    'phone_number' => $item->phone,
                    'branch_id' => $item->id_office,
                    'created_at' => date('Y-m-d H:i:s', $item->created_on),
                    'updated_at' => now(),
                    'deleted_at' => $item->active ? null : now(),
                    'deleted_by' => $item->active ? null : 1, // Asumsi dihapus oleh admin jika tidak aktif
                ]
            );
        }

        $maxId = DB::table('users')->max('id');

        // Reset Auto Increment ke ID terbesar + 1
        if ($maxId) {
            DB::statement("ALTER TABLE users AUTO_INCREMENT = " . ($maxId + 1));
        }
    }

    private function migratePlateTypes()
    {
        $this->info('Migrating: Plate Types...');
        $old = DB::connection('mysql_old')->table('plat_types')->get();
        foreach ($old as $item) {
            DB::table('plate_types')->updateOrInsert(
                ['id' => $item->id],
                ['name' => $item->name, 'created_at' => now()]
            );
        }
    }

    private function migrateSizes()
    {
        $this->info('Migrating: Sizes...');
        $old = DB::connection('mysql_old')->table('plat_sizes')->get();
        foreach ($old as $item) {
            DB::table('sizes')->updateOrInsert(
                ['id' => $item->id],
                ['value' => $item->size, 'created_at' => now()]
            );
        }
    }

    private function migrateMachineTypes()
    {
        $this->info('Migrating: Machine Types...');
        $old = DB::connection('mysql_old')->table('cutting_types')->get();
        foreach ($old as $item) {
            DB::table('machine_types')->updateOrInsert(
                ['id' => $item->id],
                ['name' => $item->name, 'created_at' => now()]
            );
        }
    }

    private function migrateCustomers()
    {
        $this->info('Migrating: Customers...');
        $old = DB::connection('mysql_old')->table('customers')->get();
        foreach ($old as $item) {
            // Cari ID user baru yang tadinya adalah Sales ID ini
            $newSalesUser = DB::table('sales_profiles')
                ->where('old_sales_id', $item->id_sales)
                ->first();

            DB::table('customers')->updateOrInsert(
                ['id' => $item->id],
                [
                    'full_name' => Str::title($item->name),
                    'contact_name' => Str::title($item->name_contact),
                    'phone_number' => $item->phone,
                    'address' => $item->address,
                    'branch_id' => $item->id_office,
                    'sales_id' => $newSalesUser ? $newSalesUser->user_id : null,
                    'created_at' => $item->created_on,
                    'created_by' => $item->created_by ?: 1,
                    'deleted_at' => $item->deleted ? now() : null,
                    'deleted_by' => $item->deleted ? 1 : null, // Asumsi dihapus oleh admin jika deleted = true
                ]
            );
        }
    }

    private function migrateSales()
    {
        $this->info('Migrating: Sales to Users...');
        $salesRole = DB::table('roles')->where('name', 'sales')->first();
        $oldSales = DB::connection('mysql_old')->table('sales')->get();

        foreach ($oldSales as $item) {
            // Buat username & email yang unik dengan bantuan ID lama
            $slug = Str::slug($item->name);
            $username = "sales." . $slug . "." . $item->id; // Pasti unik karena ada ID lama

            // Cek email, jika kosong buat dummy yang unik
            $email = !empty($item->email) ? $item->email : "{$slug}.s{$item->id}@pioneer.local";

            // Gunakan updateOrInsert pada users berdasarkan username/email agar tidak crash
            // Namun lebih baik pakai insertGetId dengan try-catch
            try {
                $newUserId = DB::table('users')->insertGetId([
                    'name' => Str::title($item->name),
                    'email' => $email,
                    'username' => $username,
                    'password' => bcrypt($username), // Default password = username
                    'role_id' => $salesRole->id ?? 2,
                    'branch_id' => $item->id_office,
                    'created_at' => is_numeric($item->created_on) ? date('Y-m-d H:i:s', $item->created_on) : now(),
                    'updated_at' => now(),
                ]);

                DB::table('sales_profiles')->updateOrInsert(
                    ['old_sales_id' => $item->id], // Simpan ID lama untuk mapping customer
                    [
                        'user_id' => $newUserId,
                        'nik' => $item->nik,
                        'email' => $item->email, // Email asli (boleh null/kosong di profile)
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            } catch (\Exception $e) {
                $this->error("Gagal migrasi sales: {$item->name}. Error: " . $e->getMessage());
            }
        }

        // Ambil ID terbesar dari tabel users setelah semua sales masuk
        $maxUserId = DB::table('users')->max('id');

        // Ambil ID terbesar dari tabel users setelah semua sales masuk
        if ($maxUserId) {
            // Reset auto increment ke angka berikutnya
            DB::statement("ALTER TABLE users AUTO_INCREMENT = " . ($maxUserId + 1));
        }

        // Jangan lupa reset juga untuk tabel sales_profiles jika kamu insert ID manual di sana
        $maxProfileId = DB::table('sales_profiles')->max('id');
        if ($maxProfileId) {
            DB::statement("ALTER TABLE sales_profiles AUTO_INCREMENT = " . ($maxProfileId + 1));
        }
    }

    private function migratePlateVariants()
    {
        $this->info('Migrating: Plate Variants...');
        DB::table('plate_variants')->truncate();
        DB::table('stock_movements')->truncate();
        DB::table('plate_price_histories')->truncate();
        // Di DB baru, plate_variants menghubungkan branch, plate_type, dan size.
        // Di DB lama, data ini tersebar di plat_prices.
        $oldPrices = DB::connection('mysql_old')->table('plat_prices')->where('deleted', 0)->get();

        foreach ($oldPrices as $old) {
            // Cari ID size yang value-nya sama (misal '0.5')
            $size = DB::table('sizes')->where('value', $old->size)->first();

            // Cari ID plate_type yang sesuai dengan id_plat
            $plateType = DB::table('plate_types')->where('id', $old->id_plat)->first();

            $variant = PlateVariant::updateOrCreate(
                ['id' => $old->id], // Kondisi pencarian
                [
                    'branch_id' => $old->id_office,
                    'plate_type_id' => $plateType->id,
                    'size_id' => $size->id,
                    'is_active' => 1,
                    'created_at' => $old->created_on,
                    'updated_at' => $old->updated_on ?: now(),
                ]
            );

            DB::table('stock_movements')->insert(
                [
                    'plate_variant_id' => $variant->id,
                    'qty' => $old->qty,
                    'type' => 'IN',
                    'description' => 'Initial stock from legacy data',
                    'user_id' => 3,
                    'created_at' => now()
                ]
            );
        }

        $maxId = DB::table('plate_variants')->max('id');
        if ($maxId) {
            // Reset auto increment ke angka berikutnya
            DB::statement("ALTER TABLE plate_variants AUTO_INCREMENT = " . ($maxId + 1));
        }

        $oldPriceHistory = DB::connection('mysql_old')
            ->table('office_plat_order as a')
            ->select('a.*')
            ->selectRaw("
                        IFNULL((
                            SELECT b.price_purchase 
                            FROM office_plat_order b 
                            WHERE b.id_office = a.id_office 
                            AND b.id_plat = a.id_plat 
                            AND b.size = a.size 
                            AND b.transacted_on < a.transacted_on
                            ORDER BY b.transacted_on DESC, b.id DESC 
                            LIMIT 1
                        ), 0) AS purchase_old
                    ")
            ->selectRaw("
                        IFNULL((
                            SELECT b.price_sale 
                            FROM office_plat_order b 
                            WHERE b.id_office = a.id_office 
                            AND b.id_plat = a.id_plat 
                            AND b.size = a.size 
                            AND b.transacted_on < a.transacted_on
                            ORDER BY b.transacted_on DESC, b.id DESC 
                            LIMIT 1
                        ), 0) AS sale_old
                    ")
            ->orderBy('a.id_office')
            ->orderBy('a.id_plat')
            ->orderBy('a.size')
            ->orderBy('a.id')
            ->get();
        foreach ($oldPriceHistory as $old) {
            $platVariant = DB::table('plate_variants as pv')
                ->select('pv.id')
                ->leftJoin('sizes', 'pv.size_id', '=', 'sizes.id')
                ->where('pv.branch_id', $old->id_office)
                ->where('sizes.value', $old->size)
                ->where('pv.plate_type_id', $old->id_plat)->first();
            if ($platVariant) {
                DB::table('plate_price_histories')->insert(
                    [
                        'plate_variant_id' => $platVariant->id,
                        'old_price' => $old->purchase_old,
                        'new_price' => $old->price_purchase,
                        'type' => 'BUY',
                        'created_at' => $old->transacted_on,
                        'user_id' => $old->handle_by_id ?: 1,
                    ]
                );
                DB::table('plate_price_histories')->insert(
                    [
                        'plate_variant_id' => $platVariant->id,
                        'old_price' => $old->sale_old,
                        'new_price' => $old->price_sale,
                        'type' => 'SELL',
                        'created_at' => $old->transacted_on,
                        'user_id' => $old->handle_by_id ?: 1,
                    ]
                );
            }
        }

    }

    private function migrateCuttingPrices()
    {
        $this->info('Migrating: Cutting Prices...');
        DB::table('cutting_prices')->truncate();

        $oldPrices = DB::connection('mysql_old')->table('service_prices as cp')
            ->select('cp.*', 'ps.id as size_id')
            ->leftJoin('plat_sizes as ps', function ($join) {
                $join->on(
                    DB::raw('CAST(ps.size AS DECIMAL(10,2))'),
                    '=',
                    DB::raw('CAST(cp.size AS DECIMAL(10,2))')
                );
            })
            ->get();

        foreach ($oldPrices as $old) {
            DB::table('cutting_prices')->insert(
                [
                    'id' => $old->id,
                    'machine_type_id' => $old->id_cutting_type,
                    'plate_type_id' => $old->id_plat,
                    'size_id' => $old->size_id,
                    'price_easy' => $old->easy,
                    'price_medium' => $old->medium,
                    'price_difficult' => $old->difficult,
                    'price_per_minute' => $old->per_minute,
                    'discount_pct' => $old->discount,
                    'is_active' => $old->deleted ? 0 : 1,
                    'created_at' => $old->created_on,
                    'updated_at' => null,
                ]
            );
        }
    }

    private function migrateInvoices()
    {
        $this->info('Migrating: Invoices...');
        DB::table('invoices')->truncate();
        DB::table('invoice_items')->truncate();

        $oldTransactions = DB::connection('mysql_old')
            ->table('transactions as tr')
            ->leftJoin('accounting_invoices as ac', 'ac.id_transaction', '=', 'tr.id')
            ->select([
                'tr.id',
                'ac.id AS id_invoice',
                'ac.invoice_number',
                'tr.id_office',
                'tr.id_customer',
                'tr.handle_by_id',
                'tr.transacted_on',
                DB::raw("
                    CASE 
                        WHEN ac.status = 5 THEN 'Cancel'
                        WHEN ac.status = 4 THEN 'Completed'
                        WHEN ac.status = 0 THEN 'Pending'
                        ELSE 'In-process'
                    END AS status
                "),
                'ac.amount',
                'ac.discount',
                'ac.discount_amount'
            ])
            ->where('tr.transaction_type', 1)
            ->orderBy('tr.id', 'asc')
            ->get();

        foreach ($oldTransactions as $old) {
            $id_invoice = $old->id_invoice;
            $created_at = $old->transacted_on;

            DB::table('invoices')->insert(
                [
                    'id' => $id_invoice,
                    'invoice_number' => $old->invoice_number,
                    'invoice_type' => 'sales',
                    'source_type' => null,
                    'source_id' => null,
                    'branch_id' => $old->id_office,
                    'customer_id' => $old->id_customer,
                    'machine_id' => null, // karena di DB lama tidak ada field mesin, bisa dikosongkan atau set ke default
                    'user_id' => $old->handle_by_id,
                    'transaction_date' => $old->transacted_on,
                    'status' => $old->status,
                    'total_amount' => $old->amount,
                    'discount_pct' => $old->discount,
                    'discount_amount' => $old->discount_amount,
                    'grand_total' => $old->amount - $old->discount_amount,
                    'created_at' => $old->transacted_on
                ]
            );

            $invoiceItems = DB::connection('mysql_old')->table('accounting_invoice_items as aci')
                ->leftJoin('service_prices as sp', 'sp.id', '=', 'aci.id_service')
                ->leftJoin('plat_prices as pp', 'pp.id', '=', 'aci.id_plat')
                ->select([
                    // Menentukan product_type
                    DB::raw("
                        CASE 
                            WHEN aci.id_plat > 0 THEN 'plate' 
                            WHEN aci.id_service > 0 THEN 'cutting' 
                        END AS product_type
                    "),

                    'aci.id_plat as plate_variant_id',
                    'aci.id_service as cutting_price_id',
                    'sp.id_cutting_type',
                    'sp.id_plat as cutting_plate_type_id',
                    'sp.size as cutting_size',
                    'pp.id_plat as plate_type_id',
                    'pp.size as plate_size',

                    // Menentukan pricing_mode berdasarkan kecocokan harga di tabel service_prices
                    DB::raw("
                        CASE 
                            WHEN aci.id_service > 0 AND aci.price = sp.easy THEN 'easy'
                            WHEN aci.id_service > 0 AND aci.price = sp.medium THEN 'medium'
                            WHEN aci.id_service > 0 AND aci.price = sp.difficult THEN 'difficult'
                            WHEN aci.id_service > 0 AND aci.price = sp.per_minute THEN 'per-minute'
                        END AS pricing_mode
                    "),

                    'aci.qty',
                    'aci.minutes',
                    'aci.price',
                    'aci.discount as discount_pct',
                    'aci.discount_amount',

                    // Kalkulasi Subtotal (Amount dikurangi diskon)
                    DB::raw("(aci.amount - aci.discount_amount) AS subtotal")
                ])
                ->where('aci.id_invoice', $id_invoice)
                ->get();

            foreach ($invoiceItems as $item) {
                $itemType = $item->product_type;
                $sourceType = $this->resolveLegacyInvoiceItemSourceType($item->product_type);
                $sourceId = $item->product_type === 'plate'
                    ? $item->plate_variant_id
                    : ($item->product_type === 'cutting' ? $item->cutting_price_id : null);
                $description = $this->buildLegacyInvoiceItemDescription($item);
                $unit = $this->resolveLegacyInvoiceItemUnit($item->product_type, $item->pricing_mode);

                DB::table('invoice_items')->insert([
                    'invoice_id' => $id_invoice,
                    'product_type' => $item->product_type,
                    'item_type' => $itemType,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'description' => $description,
                    'unit' => $unit,
                    'plate_variant_id' => $item->plate_variant_id,
                    'cutting_price_id' => $item->cutting_price_id,
                    'pricing_mode' => $item->pricing_mode,
                    'qty' => $item->qty,
                    'minutes' => $item->minutes,
                    'price' => $item->price,
                    'discount_pct' => $item->discount_pct,
                    'discount_amount' => $item->discount_amount,
                    'subtotal' => $item->subtotal,
                    'created_at' => $created_at,
                    'updated_at' => null,
                ]);
            }
        }

        $maxId = DB::table('invoices')->max('id');
        if ($maxId) {
            // Reset auto increment ke angka berikutnya
            DB::statement("ALTER TABLE invoices AUTO_INCREMENT = " . ($maxId + 1));
        }

        $maxId = DB::table('invoice_items')->max('id');
        if ($maxId) {
            // Reset auto increment ke angka berikutnya
            DB::statement("ALTER TABLE invoice_items AUTO_INCREMENT = " . ($maxId + 1));
        }

    }

    private function resolveLegacyInvoiceItemSourceType(?string $productType): ?string
    {
        return match ($productType) {
            'plate' => 'plate_variant',
            'cutting' => 'cutting_price',
            default => null,
        };
    }

    private function resolveLegacyInvoiceItemUnit(?string $productType, ?string $pricingMode): ?string
    {
        if ($productType === 'cutting' && $pricingMode === 'per-minute') {
            return 'menit';
        }

        return match ($productType) {
            'plate' => 'lembar',
            'cutting' => 'pcs',
            default => null,
        };
    }

    private function buildLegacyInvoiceItemDescription(object $item): ?string
    {
        if ($item->product_type === 'plate') {
            $plateTypeName = DB::table('plate_types')->where('id', $item->plate_type_id)->value('name') ?? 'Plat';
            $sizeValue = $item->plate_size ?? '';

            return trim($plateTypeName . ' ' . $sizeValue);
        }

        if ($item->product_type === 'cutting') {
            $machineTypeName = DB::table('machine_types')->where('id', $item->id_cutting_type)->value('name') ?? 'Cutting';
            $plateTypeName = DB::table('plate_types')->where('id', $item->cutting_plate_type_id)->value('name') ?? '';
            $sizeValue = $item->cutting_size ?? '';
            $pricingMode = $this->formatLegacyPricingMode($item->pricing_mode);

            return trim(implode(' / ', array_filter([
                $machineTypeName,
                $plateTypeName,
                $sizeValue,
                $pricingMode,
            ])));
        }

        return null;
    }

    private function formatLegacyPricingMode(?string $pricingMode): string
    {
        return match ($pricingMode) {
            'easy' => 'Easy',
            'medium' => 'Medium',
            'difficult' => 'Difficult',
            'per-minute' => 'Per Menit',
            default => '',
        };
    }

    private function migratePayments()
    {
        $this->info('Migrating: Payments...');

        DB::table('payments')->truncate();
        DB::table('payment_files')->truncate();

        $oldPayments = DB::connection('mysql_old')->table('transactions as tr')
            ->leftJoin('accounting_payments as ap', 'ap.id_transaction', '=', 'tr.id')
            ->leftJoin('payment_methods as pm', 'pm.id', '=', 'ap.payment_method_id')
            ->leftJoin('accounting_payment_invoices as api', 'api.id_payment', '=', 'ap.id')
            ->select([
                'api.id_payment', // Ini ID lama
                'api.id_invoice',
                'tr.id_office',
                'api.amount',
                'pm.code',
                'api.is_down_payment',
                'tr.transacted_on',
                'tr.handle_by_id'
            ])
            ->where('tr.transaction_type', 2)
            ->whereNotNull('api.id_payment')
            ->get();

        foreach ($oldPayments as $old) {
            // 1. Insert ke tabel baru tanpa memaksakan ID lama agar tidak Duplicate Entry
            // Kita gunakan insertGetId untuk dapat ID barunya
            $newPaymentId = DB::table('payments')->insertGetId([
                'invoice_id'     => $old->id_invoice,
                'branch_id'      => $old->id_office,
                'amount'         => $old->amount,
                'payment_method' => Str::title($old->code),
                'is_dp'          => $old->is_down_payment,
                'payment_date'   => $old->transacted_on,
                'user_id'        => $old->handle_by_id,
                'created_at'     => $old->transacted_on,
                'updated_at'     => $old->transacted_on,
                // Opsional: tambahkan kolom 'old_payment_id' di tabel payments kamu jika ingin tracking
            ]);

            // 2. Ambil Attachment berdasarkan ID Payment LAMA
            $oldPaymentAttachments = DB::connection('mysql_old')->table('accounting_payment_attachments as apa')
                ->select([
                    'apa.original_name',
                    'apa.encrypted_name',
                    'apa.ext',
                    DB::raw("
                        CASE LOWER(apa.ext)
                            WHEN '.jpg'  THEN 'image/jpeg'
                            WHEN '.jpeg' THEN 'image/jpeg'
                            WHEN '.jfif' THEN 'image/jpeg'
                            WHEN '.png'  THEN 'image/png'
                            WHEN '.pdf'  THEN 'application/pdf'
                            WHEN '.crv'  THEN 'application/octet-stream'
                            ELSE 'application/octet-stream'
                        END AS mime_type
                    "),
                    'apa.size',
                    'apa.location'
                ])
                ->where('apa.id_payment', $old->id_payment)
                ->get();

            foreach ($oldPaymentAttachments as $oldAttachment) {
                DB::table('payment_files')->insert([
                    'payment_id'     => $newPaymentId, // PAKAI ID BARU
                    'file_path'      => 'payment-proofs/' . $old->id_payment . '/' . $oldAttachment->encrypted_name,
                    'file_name'      => $oldAttachment->original_name,
                    'file_extension' => ltrim($oldAttachment->ext, '.'), // Menghapus titik di depan
                    'file_size'      => $oldAttachment->size,
                    'mime_type'      => $oldAttachment->mime_type,
                    'created_at'     => $old->transacted_on, // Ambil dari transacted_on karena di apa mungkin tdk ada
                    'updated_at'     => $old->transacted_on,
                ]);
            }
        }
    }
}
