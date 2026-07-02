<?php

namespace App\Services;

use App\Models\Promotion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PromotionService
{
    /**
     * Get active promotions for a given position.
     */
    public function getActivePromotions(string $position = 'hero_banner')
    {
        return Promotion::active()
            ->position($position)
            ->sorted()
            ->get();
    }

    /**
     * Get dashboard stats for promotions.
     */
    public function getDashboardStats(): array
    {
        $now = now();

        $total = Promotion::count();
        $active = Promotion::where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->count();

        $scheduled = Promotion::where('is_active', true)
            ->where('starts_at', '>', $now)
            ->count();

        $expired = Promotion::where('is_active', true)
            ->where('ends_at', '<', $now)
            ->count();

        $disabled = Promotion::where('is_active', false)->count();

        $byPosition = [
            'hero_banner'      => Promotion::where('is_active', true)->where('position', 'hero_banner')->count(),
            'announcement_bar' => Promotion::where('is_active', true)->where('position', 'announcement_bar')->count(),
            'both'             => Promotion::where('is_active', true)->where('position', 'both')->count(),
        ];

        return compact('total', 'active', 'scheduled', 'expired', 'disabled', 'byPosition');
    }

    /**
     * Create a promotion with optional image uploads.
     */
    public function create(array $data, ?UploadedFile $backgroundImage = null, ?UploadedFile $mobileImage = null): Promotion
    {
        if ($backgroundImage) {
            $data['background_image'] = $backgroundImage->store('promotions', 'public');
        }

        if ($mobileImage) {
            $data['mobile_image'] = $mobileImage->store('promotions', 'public');
        }

        return Promotion::create($data);
    }

    /**
     * Update a promotion with optional image replacements.
     */
    public function update(Promotion $promotion, array $data, ?UploadedFile $backgroundImage = null, ?UploadedFile $mobileImage = null): Promotion
    {
        if ($backgroundImage) {
            // Delete old image
            if ($promotion->background_image) {
                Storage::delete($promotion->background_image);
            }
            $data['background_image'] = $backgroundImage->store('promotions', 'public');
        }

        if ($mobileImage) {
            // Delete old image
            if ($promotion->mobile_image) {
                Storage::delete($promotion->mobile_image);
            }
            $data['mobile_image'] = $mobileImage->store('promotions', 'public');
        }

        $promotion->update($data);
        return $promotion->fresh();
    }

    /**
     * Toggle the active status of a promotion.
     */
    public function toggleActive(Promotion $promotion): Promotion
    {
        $promotion->update(['is_active' => !$promotion->is_active]);
        return $promotion->fresh();
    }

    /**
     * Delete a promotion and its associated images.
     */
    public function delete(Promotion $promotion): void
    {
        $promotion->delete();
    }
}
