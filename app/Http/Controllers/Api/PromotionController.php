<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PromotionService;
use Illuminate\Http\JsonResponse;

class PromotionController extends Controller
{
    public function __construct(
        private PromotionService $promotionService
    ) {}

    /**
     * Get active hero banners.
     */
    public function heroBanners(): JsonResponse
    {
        $banners = $this->promotionService->getActivePromotions('hero_banner');

        return response()->json([
            'data' => $banners->map(fn($p) => $this->formatPromotion($p)),
        ]);
    }

    /**
     * Get active announcement bars.
     */
    public function announcementBars(): JsonResponse
    {
        $bars = $this->promotionService->getActivePromotions('announcement_bar');

        return response()->json([
            'data' => $bars->map(fn($p) => $this->formatPromotion($p)),
        ]);
    }

    /**
     * Get all active promotions.
     */
    public function all(): JsonResponse
    {
        $heroBanners = $this->promotionService->getActivePromotions('hero_banner');
        $announcementBars = $this->promotionService->getActivePromotions('announcement_bar');

        return response()->json([
            'hero_banners'       => $heroBanners->map(fn($p) => $this->formatPromotion($p)),
            'announcement_bars'  => $announcementBars->map(fn($p) => $this->formatPromotion($p)),
        ]);
    }

    /**
     * Format a promotion for the frontend.
     */
    private function formatPromotion($promotion): array
    {
        return [
            'id'                    => $promotion->id,
            'title'                 => $promotion->title,
            'subtitle'              => $promotion->subtitle,
            'description'           => $promotion->description,
            'cta_text'              => $promotion->cta_text,
            'cta_url'               => $promotion->cta_url,
            'background_image'      => $promotion->background_image,
            'background_image_url'  => $promotion->background_image_url,
            'mobile_image'          => $promotion->mobile_image,
            'mobile_image_url'      => $promotion->mobile_image_url,
            'background_color'      => $promotion->background_color,
            'text_color'            => $promotion->text_color,
            'discount_text'         => $promotion->discount_text,
            'badge'                 => $promotion->badge,
            'position'              => $promotion->position,
        ];
    }
}
