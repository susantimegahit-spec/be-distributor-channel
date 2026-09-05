<?php

namespace App\Modules\PiSetting\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PiSetting;
use App\Traits\ApiResponseFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class PiSettingController extends Controller
{
    use ApiResponseFormatter;

    /**
     * Get the active PI setting.
     */
    public function show(Request $request): JsonResponse
    {
        $userId = $request->query('user_id');
        $documentTag = $request->query('document_tag');

        $query = PiSetting::query();

        if ($userId !== null) {
            $query->where('user_id', $userId);
        } else {
            $query->whereNull('user_id');
        }

        if ($documentTag !== null) {
            $query->where('document_tag', $documentTag);
        } else {
            $query->whereNull('document_tag');
        }

        $setting = $query->first();

        // Fallback to default setting if a specific one was requested but not found
        if (!$setting && ($userId !== null || $documentTag !== null)) {
            $setting = PiSetting::whereNull('user_id')->whereNull('document_tag')->first();
        }

        // If still no setting exists (e.g. completely empty table), create a default
        if (!$setting) {
            $setting = PiSetting::create([
                'user_id' => null,
                'document_tag' => null,
                'signer_name' => 'Kushan Wijono',
                'signer_title' => 'Branch Manager',
                'signature_path' => null,
            ]);
        }

        return $this->successResponse($setting, 'Konfigurasi tanda tangan PI berhasil diambil.');
    }

    /**
     * Update the PI setting.
     */
    public function update(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'nullable|integer|exists:users,id',
            'document_tag' => 'nullable|string|max:255',
            'signer_name' => 'required|string|max:255',
            'signer_title' => 'required|string|max:255',
            'signature_file' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), [], 422);
        }

        $userId = $request->input('user_id');
        $documentTag = $request->input('document_tag');

        $setting = PiSetting::where('user_id', $userId)
            ->where('document_tag', $documentTag)
            ->first();

        if (!$setting) {
            $setting = new PiSetting();
            $setting->user_id = $userId;
            $setting->document_tag = $documentTag;
        }

        $setting->signer_name = $request->input('signer_name');
        $setting->signer_title = $request->input('signer_title');

        if ($request->hasFile('signature_file')) {
            $file = $request->file('signature_file');
            $imagePath = $file->getRealPath();

            $filename = 'pi_signature_' . time() . '.jpg';
            $targetPath = 'pi_signatures/' . $filename;

            // Ensure directory exists
            if (!Storage::disk('public')->exists('pi_signatures')) {
                Storage::disk('public')->makeDirectory('pi_signatures');
            }

            $absoluteTargetPath = Storage::disk('public')->path($targetPath);

            $img = null;
            $mime = $file->getMimeType();
            if ($mime === 'image/png') {
                $img = @imagecreatefrompng($imagePath);
            } elseif ($mime === 'image/jpeg' || $mime === 'image/jpg') {
                $img = @imagecreatefromjpeg($imagePath);
            } elseif ($mime === 'image/gif') {
                $img = @imagecreatefromgif($imagePath);
            } elseif ($mime === 'image/webp') {
                $img = @imagecreatefromwebp($imagePath);
            }

            if ($img) {
                $width = imagesx($img);
                $height = imagesy($img);
                $bg = imagecreatetruecolor($width, $height);
                $white = imagecolorallocate($bg, 255, 255, 255);
                imagefill($bg, 0, 0, $white);
                imagecopy($bg, $img, 0, 0, 0, 0, $width, $height);

                imagejpeg($bg, $absoluteTargetPath, 90);
                imagedestroy($img);
                imagedestroy($bg);

                // Delete old signature
                if ($setting->signature_path && Storage::disk('public')->exists($setting->signature_path)) {
                    Storage::disk('public')->delete($setting->signature_path);
                }

                $setting->signature_path = $targetPath;
            } else {
                // Fallback to storing original file
                $path = $file->store('pi_signatures', 'public');
                if ($setting->signature_path && Storage::disk('public')->exists($setting->signature_path)) {
                    Storage::disk('public')->delete($setting->signature_path);
                }
                $setting->signature_path = $path;
            }
        }

        $setting->save();

        return $this->successResponse($setting, 'Konfigurasi tanda tangan PI berhasil diperbarui.');
    }
}
