<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SettingUpdateRequest;
use App\Http\Resources\SettingResource;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    public function index(): JsonResponse
    {
        $settings = Setting::query()->orderBy('group')->orderBy('key')->get();

        return response()->json([
            'data' => SettingResource::collection($settings),
        ]);
    }

    public function update(SettingUpdateRequest $request): JsonResponse
    {
        foreach ($request->validatedValues() as $key => $value) {
            $setting = Setting::where('key', $key)->first();

            if (! $setting) {
                continue;
            }

            // (string) false is "", which reads back as a blank rather than a
            // stored 0; normalise booleans explicitly.
            $stored = $setting->type === 'bool'
                ? (filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0')
                : (string) $value;

            $setting->forceFill(['value' => $stored])->save();
        }

        return response()->json([
            'message' => __('Settings updated successfully.'),
            'data' => SettingResource::collection(
                Setting::query()->orderBy('group')->orderBy('key')->get()
            ),
        ]);
    }
}
