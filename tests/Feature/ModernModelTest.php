<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use nameless\CodeGenerator\Support\LaravelVersion;
use PHPUnit\Framework\Attributes\Test;

class ModernModelTest extends GeneratorTestCase
{
    private const VOUCHER = 'code:string:primary,amount:decimal,meta:json,status:enum(active,used)';

    protected array $generatedEntities = ['Voucher', 'Parcel', 'Shipment'];

    protected array $generatedTables = ['vouchers', 'parcels', 'shipments'];

    protected function tearDown(): void
    {
        foreach (glob(database_path('migrations/*_to_parcels_table.php')) ?: [] as $migration) {
            unlink($migration);
        }

        parent::tearDown();
    }

    private function generate(string $entity, string $fields, string $laravel = '12.0.0'): string
    {
        $this->instance(LaravelVersion::class, new LaravelVersion($laravel));
        Artisan::call('make:fullapi', ['name' => $entity, '--fields' => $fields]);

        return $this->model($entity);
    }

    private function model(string $entity): string
    {
        return (string) file_get_contents(app_path("Models/{$entity}.php"));
    }

    #[Test]
    public function a_laravel_12_model_keeps_its_properties_and_casts_in_a_method(): void
    {
        $model = $this->generate('Voucher', self::VOUCHER);

        $this->assertStringContainsString(<<<'PHP'
            class Voucher extends Model
            {
                use HasFactory;

                protected $primaryKey = 'code';

                public $incrementing = false;

                protected $keyType = 'string';

                protected $fillable = ['code', 'amount', 'meta', 'status'];

                protected function casts(): array
                {
                    return [
                        'amount' => 'decimal:2',
                        'meta' => 'array',
                        'status' => VoucherStatus::class,
                    ];
                }
            }
            PHP, $model);
        $this->assertStringContainsString('use App\Enums\VoucherStatus;', $model);
        $this->assertStringNotContainsString('$casts', $model);
        $this->assertStringNotContainsString('#[', $model);
    }

    #[Test]
    public function a_laravel_13_model_declares_its_key_and_fillable_columns_as_attributes(): void
    {
        $model = $this->generate('Voucher', self::VOUCHER, '13.0.0');

        $this->assertStringContainsString(<<<'PHP'
            #[Table(key: 'code', keyType: 'string', incrementing: false)]
            #[Fillable(['code', 'amount', 'meta', 'status'])]
            class Voucher extends Model
            {
                use HasFactory;

                protected function casts(): array
            PHP, $model);
        $this->assertStringContainsString('use Illuminate\Database\Eloquent\Attributes\Fillable;', $model);
        $this->assertStringContainsString('use Illuminate\Database\Eloquent\Attributes\Table;', $model);
        $this->assertStringNotContainsString('$fillable', $model);
        $this->assertStringNotContainsString('$primaryKey', $model);
    }

    #[Test]
    public function an_auto_increment_key_needs_no_table_attribute(): void
    {
        $model = $this->generate('Parcel', 'label:string', '13.0.0');

        $this->assertStringContainsString("#[Fillable(['label'])]\nclass Parcel extends Model", $model);
        $this->assertStringNotContainsString('Table', $model);
    }

    #[Test]
    public function the_model_behaves_the_same_on_the_installed_laravel(): void
    {
        $this->generate('Voucher', self::VOUCHER, app()->version());
        require_once app_path('Models/Voucher.php');

        $voucher = $this->newModel('Voucher');

        $this->assertSame('code', $voucher->getKeyName());
        $this->assertFalse($voucher->getIncrementing());
        $this->assertSame('string', $voucher->getKeyType());
        $this->assertSame(['code', 'amount', 'meta', 'status'], $voucher->getFillable());
        $this->assertSame(['amount' => 'decimal:2', 'meta' => 'array', 'status' => 'App\Enums\VoucherStatus'], $voucher->getCasts());
    }

    #[Test]
    public function two_status_fields_get_one_enum_each(): void
    {
        $this->generate('Parcel', 'status:enum(sent,lost)');
        $this->generate('Shipment', 'status:enum(pending,delivered)');

        $this->assertStringContainsString("enum ParcelStatus: string\n{\n    case Sent = 'sent';\n    case Lost = 'lost';\n}", (string) file_get_contents(app_path('Enums/ParcelStatus.php')));
        $this->assertStringContainsString("enum ShipmentStatus: string\n{\n    case Pending = 'pending';\n    case Delivered = 'delivered';\n}", (string) file_get_contents(app_path('Enums/ShipmentStatus.php')));
        $this->assertStringContainsString("'status' => ShipmentStatus::class", $this->model('Shipment'));
    }

    #[Test]
    public function fields_added_to_a_laravel_13_model_land_in_its_attribute_and_a_new_casts_method(): void
    {
        $this->generate('Parcel', 'label:string', '13.0.0');

        Artisan::call('make:fullapi', ['name' => 'Parcel', '--add-fields' => 'status:enum(sent,lost),sent_on:date']);

        $model = $this->model('Parcel');
        $this->assertStringContainsString("#[Fillable(['label', 'status', 'sent_on'])]", $model);
        $this->assertStringContainsString("    protected function casts(): array\n    {\n        return [\n            'status' => ParcelStatus::class,\n            'sent_on' => 'date',\n        ];\n    }", $model);
        $this->assertStringContainsString('use App\Enums\ParcelStatus;', $model);
        $this->assertStringContainsString(' * @property ParcelStatus $status', $model);
    }

    #[Test]
    public function fields_added_to_a_model_written_by_3x_land_in_its_casts_property(): void
    {
        $this->generate('Parcel', 'label:string');
        file_put_contents(app_path('Models/Parcel.php'), <<<'PHP'
            <?php

            namespace App\Models;

            use Illuminate\Database\Eloquent\Model;

            /**
             * @property string $label
             */
            class Parcel extends Model
            {
                protected $fillable = ['label'];

                protected $casts = [
                    'label' => 'string',
                ];
            }

            PHP);

        Artisan::call('make:fullapi', ['name' => 'Parcel', '--add-fields' => 'status:enum(sent,lost)']);

        $model = $this->model('Parcel');
        $this->assertStringContainsString("protected \$fillable = ['label', 'status'];", $model);
        $this->assertStringContainsString("    protected \$casts = [\n        'status' => ParcelStatus::class,\n        'label' => 'string',\n    ];", $model);
        $this->assertStringContainsString("use App\\Enums\\ParcelStatus;\nuse Illuminate\\Database\\Eloquent\\Model;", $model);
        $this->assertStringNotContainsString('function casts()', $model);
    }

    /**
     * The model class only exists once generated, so its type stays unknown here.
     */
    private function newModel(string $name): Model
    {
        $class = "App\\Models\\{$name}";
        $model = new $class;

        if (! $model instanceof Model) {
            $this->fail("{$class} is not a model");
        }

        return $model;
    }
}
