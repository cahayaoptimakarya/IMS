<?php

namespace Tests\Feature;

use App\Http\Middleware\PermissionMiddleware;
use App\Models\Item;
use App\Models\User;
use App\Support\MenuPermissionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class ItemBulkUpdateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('uoms')->insert([['id' => 1, 'name' => 'Pieces', 'symbol' => 'pcs'], ['id' => 2, 'name' => 'Box', 'symbol' => 'box']]);
        DB::table('item_types')->insert([['id' => 1, 'name' => 'Barang Jadi'], ['id' => 2, 'name' => 'Bahan Baku']]);
        DB::table('item_categories')->insert([['id' => 1, 'name' => 'Umum'], ['id' => 2, 'name' => 'Elektronik']]);

        $this->withoutMiddleware(PermissionMiddleware::class);
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    private function item(string $sku, array $attributes = []): Item
    {
        return Item::create($attributes + [
            'sku' => $sku, 'nama_barang' => 'Barang ' . $sku, 'koli' => 10, 'uom_id' => 1,
            'item_type_id' => 1, 'item_category_id' => 1, 'is_active' => true, 'tempat' => 'Rak A',
        ]);
    }

    /** Buat file xlsx sederhana dari baris-baris (baris pertama = header). */
    private function excel(array $rows, string $sheetTitle = 'Data'): UploadedFile
    {
        $book = new Spreadsheet();
        $book->getActiveSheet()->setTitle($sheetTitle)->fromArray($rows, null, 'A1', true);
        $path = tempnam(sys_get_temp_dir(), 'bu') . '.xlsx';
        IOFactory::createWriter($book, 'Xlsx')->save($path);

        return new UploadedFile($path, 'update.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function preview(array $fields, UploadedFile $file)
    {
        return $this->postJson(route('admin.masterdata.items.update-bulk.preview'), ['fields' => $fields, 'file' => $file]);
    }

    public function test_page_renders_with_locked_fields(): void
    {
        $this->get(route('admin.masterdata.items.update-bulk.index'))
            ->assertOk()
            ->assertSee('Pilih field yang akan di-update')
            ->assertSee('value="tempat"', false)
            ->assertDontSee('value="koli"', false)
            ->assertDontSee('value="sku"', false)
            ->assertSee('Koli (isi per koli)');
    }

    public function test_routes_require_edit_permission(): void
    {
        $resolver = app(MenuPermissionResolver::class);
        foreach (['index' => 'GET', 'template' => 'GET', 'preview' => 'POST', 'apply' => 'POST'] as $name => $method) {
            $this->assertSame('edit', $resolver->resolveAbility($method, "admin.masterdata.items.update-bulk.{$name}"));
        }
    }

    public function test_template_contains_only_selected_columns_with_current_data(): void
    {
        $this->item('SKU-2', ['nama_barang' => 'Kabel', 'item_category_id' => 2, 'panjang_koli' => 60, 'lebar_koli' => 40, 'tinggi_koli' => 30]);
        $this->item('SKU-1', ['is_active' => false]);

        $response = $this->get(route('admin.masterdata.items.update-bulk.template', [
            'fields' => ['item_category_id', 'is_active', 'dimensi_koli'],
        ]))->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'tpl') . '.xlsx';
        file_put_contents($path, $response->streamedContent() ?: $response->getFile()->getContent());
        $book = IOFactory::load($path);
        @unlink($path);

        $this->assertSame(['Data', 'Petunjuk', 'Referensi'], $book->getSheetNames());
        $data = $book->getSheetByName('Data');
        $this->assertSame(
            ['SKU', 'Info: Nama Barang', 'Kategori', 'Status', 'Panjang Koli (cm)', 'Lebar Koli (cm)', 'Tinggi Koli (cm)'],
            $data->rangeToArray('A1:G1')[0]
        );
        $this->assertSame(['SKU-1', 'Barang SKU-1', 'Umum', 'Nonaktif', null, null, null], $data->rangeToArray('A2:G2', null, false)[0]);
        $this->assertSame(['SKU-2', 'Kabel', 'Elektronik', 'Aktif'], array_slice($data->rangeToArray('A3:G3', null, false)[0], 0, 4));
        $this->assertEquals(60, $data->getCell('E3')->getValue());
        $this->assertSame('list', $data->getDataValidation('C2')->getType());
        $this->assertStringContainsString('Referensi', $data->getDataValidation('C2')->getFormula1());
    }

    public function test_template_rejects_locked_fields(): void
    {
        foreach (['sku', 'koli'] as $locked) {
            $this->getJson(route('admin.masterdata.items.update-bulk.template', ['fields' => ['tempat', $locked]]))
                ->assertStatus(422)->assertJsonValidationErrors('fields.1');
        }
        $this->getJson(route('admin.masterdata.items.update-bulk.template', ['fields' => []]))->assertStatus(422);
    }

    public function test_preview_validates_rows_without_changing_data(): void
    {
        $this->item('A-1');
        $this->item('A-2');
        $this->item('A-3');
        $this->item('A-4');
        $this->item('A-5', ['panjang_koli' => 10, 'lebar_koli' => 10, 'tinggi_koli' => 10]);

        $file = $this->excel([
            ['SKU', 'Koli', 'Tempat', 'Kategori', 'Status', 'Minimal Stok Menipis', 'Panjang Koli (cm)', 'Lebar Koli (cm)', 'Tinggi Koli (cm)', 'Kolom Lain'],
            ['a-1', 99, 'Rak B', 'elektronik', 'nonaktif', 50, '60', '40,5', 30, 'x'], // berubah (SKU beda huruf)
            ['A-2', 99, 'Rak A', 'Umum', 'Aktif', 100, null, null, null, null],         // tidak berubah
            ['A-3', null, '#kosong', 'Tidak Ada', null, -1, null, null, null, null],   // error kategori & angka
            ['A-4', null, null, null, null, null, 60, null, null, null],               // dimensi tidak lengkap
            ['A-5', null, null, null, null, null, '#KOSONG', '#KOSONG', '#KOSONG', null], // kosongkan dimensi
            ['X-9', null, 'Rak Z', null, null, null, null, null, null, null],          // tidak ditemukan
            ['A-1', null, 'Rak C', null, null, null, null, null, null, null],          // duplikat
            [null, null, null, null, null, null, null, null, null, null],              // baris kosong -> dilewati
        ]);

        $res = $this->preview(['tempat', 'item_category_id', 'is_active', 'low_stock_threshold', 'dimensi_koli'], $file)
            ->assertOk()->json();

        $this->assertSame(['total' => 7, 'changed' => 2, 'unchanged' => 1, 'error' => 3, 'not_found' => 1, 'field_changes' => 10], $res['summary']);
        $rows = collect($res['rows'])->keyBy('row');
        $this->assertSame('changed', $rows[2]['status']);
        $this->assertSame('A-1', $rows[2]['sku']);
        $this->assertContains(['column' => 'item_category_id', 'label' => 'Kategori', 'old' => 'Umum', 'new' => 'Elektronik'], $rows[2]['changes']);
        $this->assertContains(['column' => 'lebar_koli', 'label' => 'Lebar Koli (cm)', 'old' => '(kosong)', 'new' => '40,5'], $rows[2]['changes']);
        $this->assertSame('unchanged', $rows[3]['status']);
        $this->assertCount(2, $rows[4]['errors']);
        $this->assertStringContainsString('Kategori', $rows[4]['errors'][0]);
        $this->assertStringContainsString('lengkap', $rows[5]['errors'][0]);
        $this->assertSame('changed', $rows[6]['status']);
        $this->assertSame('not_found', $rows[7]['status']);
        $this->assertStringContainsString('duplikat', $rows[8]['errors'][0]);
        $this->assertTrue(collect($res['warnings'])->contains(fn ($w) => str_contains($w, 'Koli tidak dapat diubah')));
        $this->assertTrue(collect($res['warnings'])->contains(fn ($w) => str_contains($w, 'Kolom Lain')));
        $this->assertNotNull($res['token']);

        // Pratinjau tidak mengubah data
        $this->assertSame('Rak A', Item::where('sku', 'A-1')->value('tempat'));
    }

    public function test_apply_updates_only_selected_fields_and_never_koli_or_sku(): void
    {
        $item = $this->item('A-1', ['deskripsi' => 'lama']);

        $file = $this->excel([
            ['SKU', 'Nama Barang', 'Koli', 'Tempat', 'UOM', 'Tipe Item', 'Deskripsi', 'Lead Time PO (hari)'],
            ['A-1', 'Nama Baru', 500, '#KOSONG', 'box', 'Bahan Baku', 'deskripsi baru', 14],
        ]);
        $token = $this->preview(['nama_barang', 'tempat', 'uom_id', 'item_type_id'], $file)->assertOk()->json('token');

        $this->postJson(route('admin.masterdata.items.update-bulk.apply'), ['token' => $token])
            ->assertOk()->assertJson(['success' => true, 'updated' => 1]);

        $item->refresh();
        $this->assertSame('Nama Baru', $item->nama_barang);
        $this->assertNull($item->tempat);
        $this->assertSame(2, (int) $item->uom_id);
        $this->assertSame(2, (int) $item->item_type_id);
        $this->assertSame('lama', $item->deskripsi);          // tidak dipilih -> tidak berubah
        $this->assertSame(7, (int) $item->lead_time_po_days); // tidak dipilih -> tidak berubah
        $this->assertSame(10, (int) $item->koli);              // koli terkunci
        $this->assertSame('A-1', $item->sku);
        $this->assertDatabaseHas('user_activities', ['menu' => 'items', 'activity' => 'updated']);

        // Token sekali pakai
        $this->postJson(route('admin.masterdata.items.update-bulk.apply'), ['token' => $token])->assertStatus(422);
    }

    public function test_apply_skips_items_changed_after_preview(): void
    {
        $a = $this->item('A-1');
        $b = $this->item('A-2');
        $file = $this->excel([['SKU', 'Tempat'], ['A-1', 'Rak X'], ['A-2', 'Rak Y']]);
        $token = $this->preview(['tempat'], $file)->json('token');

        $b->update(['tempat' => 'Diubah user lain']);

        $res = $this->postJson(route('admin.masterdata.items.update-bulk.apply'), ['token' => $token])->assertOk()->json();
        $this->assertSame(1, $res['updated']);
        $this->assertSame(['A-2'], $res['conflicts']);
        $this->assertSame('Rak X', $a->fresh()->tempat);
        $this->assertSame('Diubah user lain', $b->fresh()->tempat);
    }

    public function test_token_cannot_be_used_by_another_user(): void
    {
        $this->item('A-1');
        $token = $this->preview(['tempat'], $this->excel([['SKU', 'Tempat'], ['A-1', 'Rak X']]))->json('token');

        $this->actingAs(User::factory()->create());
        $this->postJson(route('admin.masterdata.items.update-bulk.apply'), ['token' => $token])->assertStatus(422);
        $this->assertSame('Rak A', Item::where('sku', 'A-1')->value('tempat'));
    }

    public function test_file_level_errors(): void
    {
        $this->item('A-1');

        $this->preview(['tempat', 'deskripsi'], $this->excel([['SKU', 'Tempat'], ['A-1', 'x']]))
            ->assertStatus(422)->assertJsonFragment(['success' => false])
            ->assertJsonPath('message', fn ($m) => str_contains($m, '"Deskripsi"'));

        $this->preview(['tempat'], $this->excel([['Kode', 'Tempat'], ['A-1', 'x']]))
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'SKU'));

        $this->preview(['tempat'], $this->excel([['SKU', 'Tempat']]))
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'tidak berisi data'));

        $this->preview(['koli'], $this->excel([['SKU', 'Koli'], ['A-1', 5]]))
            ->assertStatus(422)->assertJsonValidationErrors('fields.0');
    }

    public function test_round_trip_with_downloaded_template(): void
    {
        $this->item('A-1');
        $this->item('A-2');

        $response = $this->get(route('admin.masterdata.items.update-bulk.template', ['fields' => ['tempat']]));
        $path = tempnam(sys_get_temp_dir(), 'rt') . '.xlsx';
        file_put_contents($path, $response->streamedContent() ?: $response->getFile()->getContent());

        $book = IOFactory::load($path);
        $book->getSheetByName('Data')->setCellValue('C2', 'Rak Baru'); // A-1
        IOFactory::createWriter($book, 'Xlsx')->save($path);

        $res = $this->preview(['tempat'], new UploadedFile($path, 'tpl.xlsx', null, null, true))->assertOk()->json();
        $this->assertSame(1, $res['summary']['changed']);
        $this->assertSame(1, $res['summary']['unchanged']);
        $this->assertSame([], $res['warnings']); // kolom "Info: Nama Barang" diabaikan tanpa peringatan

        $this->postJson(route('admin.masterdata.items.update-bulk.apply'), ['token' => $res['token']])->assertOk();
        $this->assertSame('Rak Baru', Item::where('sku', 'A-1')->value('tempat'));
        $this->assertSame('Rak A', Item::where('sku', 'A-2')->value('tempat'));
    }
}
