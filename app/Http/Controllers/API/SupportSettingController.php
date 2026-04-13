<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\SupportSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SupportSettingController extends Controller
{
    // Public: Get Settings
    public function index()
    {
        $settings = SupportSetting::pluck('value', 'key');

        return response()->json([
            // Site Status
            'site_status' => $settings->get('site_status', 'open'),

            // Support settings
            'individual_support_enabled' => $settings->get('individual_support_enabled') === 'true',
            'institutional_support_enabled' => $settings->get('institutional_support_enabled') === 'true',

            // Module settings
            'module_articles_enabled' => $settings->get('module_articles_enabled', 'true') === 'true',
            'module_audios_enabled' => $settings->get('module_audios_enabled', 'true') === 'true',
            'module_visuals_enabled' => $settings->get('module_visuals_enabled', 'true') === 'true',
            'module_galleries_enabled' => $settings->get('module_galleries_enabled', 'true') === 'true',
            'module_library_enabled' => $settings->get('module_library_enabled', 'true') === 'true',
            'module_links_enabled' => $settings->get('module_links_enabled', 'true') === 'true',
        ]);
    }

    // Admin: Update Settings
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'key' => 'required|in:individual_support_enabled,institutional_support_enabled,module_articles_enabled,module_audios_enabled,module_visuals_enabled,module_galleries_enabled,module_library_enabled,module_links_enabled,site_status',
            'value' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Validate value based on key
        if ($request->key === 'site_status') {
            if (!in_array($request->value, ['open', 'closed'])) {
                return response()->json(['errors' => ['value' => ['Invalid value for site_status. Must be open or closed.']]], 422);
            }
        } else {
             if (!in_array($request->value, ['true', 'false'])) {
                 return response()->json(['errors' => ['value' => ['Value must be true or false']]], 422);
             }
        }

        SupportSetting::updateOrCreate(
            ['key' => $request->key],
            ['value' => $request->value]
        );

        return response()->json(['message' => 'تم تحديث الإعدادات بنجاح']);
    }

    // Admin: Update All Settings
    public function updateAll(Request $request)
    {
        // Handle GET Request: Return current status
        if ($request->isMethod('get')) {
            return $this->index();
        }

        $validator = Validator::make($request->all(), [
            'value' => 'required|in:true,false',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $keys = ['individual_support_enabled', 'institutional_support_enabled'];

        foreach ($keys as $key) {
            SupportSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $request->value]
            );
        }

        return response()->json(['message' => 'تم تحديث جميع إعدادات الدعم بنجاح']);
    }

    /**
     * Get Site Status
     * GET /api/settings/site-status
     */
    public function getSiteStatus()
    {
        $status = SupportSetting::where('key', 'site_status')->value('value') ?? 'open';
        return response()->json(['status' => $status]);
    }

    /**
     * Update Site Status (Open/Close)
     * POST /api/settings/site-status
     */
    public function updateSiteStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:open,closed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        SupportSetting::updateOrCreate(
            ['key' => 'site_status'],
            ['value' => $request->status]
        );

        return response()->json([
            'message' => 'تم تحديث حالة الموقع بنجاح',
            'status' => $request->status
        ]);
    }
}

