<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\SizeGuide;
use App\Support\Measurement;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Needs the 2026-11-06_size-guide-dual-unit.sql column on the local MySQL test DB; every test is rolled back. */
class SizeGuideUnitTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->actingAs(AdminUser::firstOrFail(), 'admin');
    }

    private function payload(array $over = []): array
    {
        return array_merge([
            'title' => 'QA Guide', 'key' => 'qa-unit-guide', 'status' => 'active',
            'attributes' => [['name' => 'Chest'], ['name' => 'Length']],
            'sizes' => [['label' => 'M', 'values' => ['38', '28.5']], ['label' => 'L', 'values' => ['40.25', '29']]],
        ], $over);
    }

    public function test_form_defaults_to_inch(): void
    {
        $this->get('/size-guides/create')->assertOk()->assertSee('Measurement Unit')
            ->assertSee('<option value="in" selected', false);
    }

    public function test_save_stores_unit_and_numbers_as_entered(): void
    {
        $this->post('/size-guides', $this->payload(['measurement_unit' => 'in']))->assertRedirect();
        $g = SizeGuide::where('key', 'qa-unit-guide')->firstOrFail();
        $this->assertSame('in', $g->measurement_unit);
        $this->assertSame(['in', 'in'], $g->sizeAttributes->pluck('unit')->all());
        $this->assertEquals(40.25, (float) DB::table('size_guide_values')->where('size_guide_id', $g->id)->where('size_label', 'L')->orderBy('sort_order')->value('measurement_value'));
    }

    public function test_changing_unit_relabels_without_touching_posted_numbers(): void
    {
        $this->post('/size-guides', $this->payload(['measurement_unit' => 'in']));
        $g = SizeGuide::where('key', 'qa-unit-guide')->firstOrFail();
        $this->put("/size-guides/$g->id", $this->payload(['measurement_unit' => 'cm']))->assertRedirect();
        $g->refresh();
        $this->assertSame('cm', $g->measurement_unit);
        $this->assertSame(['cm', 'cm'], $g->sizeAttributes->pluck('unit')->all());
        $this->assertEquals(38.0, (float) $g->sizeValues->firstWhere('size_label', 'M')->measurement_value);
    }

    public function test_product_association_survives_edit(): void
    {
        $this->post('/size-guides', $this->payload(['measurement_unit' => 'in']));
        $g = SizeGuide::where('key', 'qa-unit-guide')->firstOrFail();
        $pid = DB::table('products')->value('id');
        DB::table('products')->where('id', $pid)->update(['size_guide_id' => $g->id]);
        $this->put("/size-guides/$g->id", $this->payload(['measurement_unit' => 'cm']))->assertRedirect();
        $this->assertSame($g->id, (int) DB::table('products')->where('id', $pid)->value('size_guide_id'));
    }

    public function test_rejects_bad_unit_and_malformed_values(): void
    {
        $this->post('/size-guides', $this->payload(['measurement_unit' => 'yd']))->assertSessionHasErrors('measurement_unit');
        foreach (['abc', '38-', '40-38', '1.234'] as $bad) {
            $p = $this->payload(['measurement_unit' => 'in', 'sizes' => [['label' => 'M', 'values' => [$bad, '28']]]]);
            $this->post('/size-guides', $p)->assertSessionHasErrors('sizes.0.values.0');
        }
    }

    // ---- ranges, stored vs display unit ----------------------------------------------------------------

    /** Inserts a legacy-shaped guide: per-attribute stored unit + raw values, guide column left at its 'in' default. */
    private function legacy(string $key, array $attrs, array $row, string $column = 'in'): SizeGuide
    {
        $g = SizeGuide::create(['title' => $key, 'key' => $key, 'status' => 'active', 'measurement_unit' => $column]);
        foreach ($attrs as $i => [$name, $unit]) {
            $id = DB::table('size_guide_attributes')->insertGetId(['size_guide_id' => $g->id, 'attribute_name' => $name, 'attribute_key' => strtolower($name), 'unit' => $unit, 'sort_order' => $i + 1]);
            DB::table('size_guide_values')->insert(['size_guide_id' => $g->id, 'size_label' => 'M', 'attribute_id' => $id, 'measurement_value' => $row[$i], 'sort_order' => $i + 1]);
        }

        return $g->fresh();
    }

    public function test_cm_single_value_shown_in_inch_once(): void
    {
        $this->assertSame('27.99', Measurement::convert('71.1', 'cm', 'in'));
        $this->assertSame('27.99"', Measurement::format(Measurement::convert('71.1', 'cm', 'in'), 'in'));
    }

    public function test_cm_range_shown_in_inch(): void
    {
        $this->assertSame('27.99-30', Measurement::convert('71.1-76.2', 'cm', 'in'));
        $this->assertSame('27.99"-30"', Measurement::format(Measurement::convert('71.1-76.2', 'cm', 'in'), 'in'));
        $this->assertSame('71.1-76.2', Measurement::format('71.1 - 76.2', 'cm'));
    }

    public function test_inch_single_and_range_shown_as_stored(): void
    {
        $this->assertSame('38"', Measurement::format(Measurement::convert('38.00', 'in', 'in'), 'in'));
        $this->assertSame('28"-30"', Measurement::format(Measurement::convert('28-30', 'in', 'in'), 'in'));
        $this->assertSame('71.12-76.2', Measurement::convert('28-30', 'in', 'cm'));
    }

    public function test_display_truncates_to_one_decimal_never_rounds(): void
    {
        foreach ([[13.82, '13.8'], [14.21, '14.2'], [35.96, '35.9'], [37.04, '37.0'], [14, '14.0'], [2.3, '2.3'], [0.29, '0.2']] as [$n, $want]) {
            $this->assertSame($want, Measurement::truncate1((float) $n));
        }
    }

    public function test_inch_display_truncates_each_end(): void
    {
        $this->assertSame('35.9"-37.0"', Measurement::display('35.96-37.04', 'in', 'in'));
        $this->assertSame('28.1"-30.9"', Measurement::display('28.1-30.9', 'in', 'in'));
        $this->assertSame('37.6"', Measurement::display('37.6', 'in', 'in'));
        $this->assertSame('14.0"', Measurement::display('14', 'in', 'in'));
        $this->assertSame('27.9"-30.0"', Measurement::display('71.1-76.2', 'cm', 'in')); // converted first, then truncated
        $this->assertSame('34.0"', Measurement::display('86.4', 'cm', 'in'));
    }

    public function test_cm_display_also_truncates_to_one_decimal(): void
    {
        $this->assertSame('71.1-76.2', Measurement::display('71.1-76.2', 'cm', 'cm'));
        $this->assertSame('35.9-37.0', Measurement::display('35.96-37.04', 'cm', 'cm'));
        $this->assertSame('91.3-94.0', Measurement::display('35.96-37.04', 'in', 'cm')); // 91.3384, 94.0816
        $this->assertSame('86.0', Measurement::display('86', 'cm', 'cm'));
    }

    public function test_show_page_truncates_but_database_is_untouched(): void
    {
        $g = $this->legacy('round-demo', [['Chest', 'in']], ['35.96-37.04'], 'in');
        $this->get("/size-guides/$g->id?unit=in")->assertOk()->assertSee('35.9&quot;-37.0&quot;', false);
        $this->get("/size-guides/$g->id?unit=cm")->assertOk()->assertSee('91.3-94.0');
        $this->assertSame('35.96-37.04', DB::table('size_guide_values')->where('size_guide_id', $g->id)->value('measurement_value'));
        $this->assertSame('35.96-37.04', $this->get("/size-guides/$g->id/edit")->viewData('matrix')['sizes'][0]['values'][0]); // editor keeps exact data
    }

    public function test_switching_always_derives_from_the_original(): void
    {
        $orig = '71.1-76.2';
        $in = Measurement::convert($orig, 'cm', 'in');
        $this->assertSame($orig, Measurement::convert($orig, 'cm', 'cm'));   // back to the original unit = the original, not in->cm
        $this->assertNotSame($orig, Measurement::convert($in, 'in', 'cm'));    // converting the converted value WOULD drift (71.09-76.2)
    }

    public function test_legacy_cm_guide_with_default_column_is_not_read_as_inch(): void
    {
        $g = $this->legacy('legacy-cm', [['Chest', 'cm'], ['Waist', 'cm']], ['71.1-76.2', '86.4'], 'in');   // column wrongly 'in'
        $this->assertSame('cm', $g->load('sizeAttributes')->storedUnit());

        $edit = $this->get("/size-guides/$g->id/edit")->assertOk();
        $this->assertSame('cm', $edit->viewData('matrix')['unit']);
        $this->assertSame(['71.1-76.2', '86.4'], $edit->viewData('matrix')['sizes'][0]['values']);   // nothing converted on open

        $this->get("/size-guides/$g->id?unit=in")->assertOk()->assertSee('27.9&quot;-30.0&quot;', false)->assertSee('34.0&quot;', false);
        $this->get("/size-guides/$g->id?unit=cm")->assertOk()->assertSee('71.1-76.2');
    }

    public function test_mixed_legacy_units_are_converted_once_per_attribute(): void
    {
        $g = $this->legacy('legacy-mixed', [['Chest', 'cm'], ['Length', 'inches']], ['71.1', '28'], 'in');
        $m = $this->get("/size-guides/$g->id/edit")->viewData('matrix');
        $this->assertSame('in', $m['unit']);                       // falls back to the guide column
        $this->assertSame(['27.99', '28'], $m['sizes'][0]['values']);   // cm cell converted once, inch cell untouched

        $this->get("/size-guides/$g->id?unit=cm")->assertSee('71.1');
    }

    public function test_save_and_reopen_keeps_meaning(): void
    {
        $g = $this->legacy('reopen', [['Chest', 'cm']], ['71.1-76.2'], 'cm');
        $m = $this->get("/size-guides/$g->id/edit")->viewData('matrix');
        $this->put("/size-guides/$g->id", [
            'title' => 'reopen', 'key' => 'reopen', 'status' => 'active', 'measurement_unit' => $m['unit'],
            'attributes' => $m['attributes'], 'sizes' => $m['sizes'],
        ])->assertRedirect();

        $g = $g->fresh()->load('sizeAttributes');
        $this->assertSame('cm', $g->storedUnit());
        $this->assertSame('71.1-76.2', DB::table('size_guide_values')->where('size_guide_id', $g->id)->value('measurement_value'));
    }

    public function test_range_values_are_saved_verbatim(): void
    {
        $this->post('/size-guides', $this->payload(['measurement_unit' => 'cm', 'sizes' => [['label' => 'S', 'values' => ['71.1-76.2', '86.4 - 91.4']]]]))->assertRedirect();
        $g = SizeGuide::where('key', 'qa-unit-guide')->firstOrFail();
        $this->assertSame(['71.1-76.2', '86.4-91.4'], DB::table('size_guide_values')->where('size_guide_id', $g->id)->orderBy('sort_order')->pluck('measurement_value')->all());
    }
}
