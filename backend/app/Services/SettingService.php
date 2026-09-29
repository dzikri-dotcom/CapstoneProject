<?php

namespace App\Services;

use App\Models\Setting;

/**
 * Membaca & menyimpan pengaturan sistem. Nilai yang belum ada di database
 * otomatis memakai nilai bawaan Setting::DEFAULTS.
 */
class SettingService
{
    private ?array $cache = null;

    /**
     * @return array<string, int|string|bool>
     */
    public function all(): array
    {
        if ($this->cache === null) {
            $defaults = array_map(fn (array $default) => $default['value'], Setting::DEFAULTS);
            $stored = Setting::query()
                ->whereIn('key', array_keys($defaults))
                ->get()
                ->mapWithKeys(fn (Setting $setting) => [$setting->key => $setting->typedValue()])
                ->all();

            $this->cache = array_merge($defaults, $stored);
        }

        return $this->cache;
    }

    public function get(string $key): mixed
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, int|string|bool>
     */
    public function update(array $values): array
    {
        foreach ($values as $key => $value) {
            if (! array_key_exists($key, Setting::DEFAULTS)) {
                continue;
            }

            Setting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => (string) $value, 'type' => Setting::DEFAULTS[$key]['type']],
            );
        }

        $this->cache = null;

        return $this->all();
    }
}
