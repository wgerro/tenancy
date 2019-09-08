<?php

declare(strict_types=1);

namespace Stancl\Tenancy;

use Illuminate\Database\Eloquent\Model;

/**
 * @internal Class is subject to breaking changes in minor and patch versions.
 */
class TenantModel extends Model
{
    protected $guarded = [];
    protected $primaryKey = 'id';
    public $incrementing = false;
    public $timestamps = false;

    public static function dataColumn()
    {
        return config('tenancy.storage.db.data_column', 'data');
    }

    public static function customColumns()
    {
        return config('tenancy.storage.db.custom_columns', []);
    }

    public function getConnectionName()
    {
        return config('tenancy.storage.db.connection') ?? app(DatabaseManager::class)->originalDefaultConnectionName;
    }

    public static function getAllTenants(array $uuids)
    {
        $tenants = $uuids ? static::findMany($uuids) : static::all();

        return $tenants->map([__CLASS__, 'decodeData'])->toBase();
    }

    public function decoded()
    {
        return static::decodeData($this);
    }

    /**
     * Return a tenant array with data decoded into separate keys.
     *
     * @param Tenant|array $tenant
     * @return array
     */
    public static function decodeData($tenant)
    {
        $tenant = $tenant instanceof self ? (array) $tenant->attributes : $tenant;
        $decoded = \json_decode($tenant[$dataColumn = static::dataColumn()], true);

        foreach ($decoded as $key => $value) {
            $tenant[$key] = $value;
        }

        // If $tenant[$dataColumn] has been overriden by a value, don't delete the key.
        if (! \array_key_exists($dataColumn, $decoded)) {
            unset($tenant[$dataColumn]);
        }

        return $tenant;
    }

    public function getFromData(string $key)
    {
        $this->dataArray = $this->dataArray ?? \json_decode($this->{$this->dataColumn()}, true);

        return $this->dataArray[$key] ?? null;
    }

    public function get(string $key)
    {
        return $this->attributes[$key] ?? $this->getFromData($key) ?? null;
    }

    public function getMany(array $keys): array
    {
        return array_reduce($keys, function ($result, $key) {
            $result[$key] = $this->get[$key];

            return $result;
        }, []);
    }

    public function put(string $key, $value)
    {
        if (\in_array($key, $this->customColumns())) {
            $this->update([$key => $value]);
        } else {
            $obj = \json_decode($this->{$this->dataColumn()});
            $obj->$key = $value;

            $this->update([$this->dataColumn() => \json_encode($obj)]);
        }

        return $value;
    }
}