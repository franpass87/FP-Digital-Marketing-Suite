<?php

declare(strict_types=1);

namespace FP\DMS\Services;

use FP\DMS\Support\Wp;
use FP\DMS\Support\Period;

/**
 * Dynamic content generation engine for templates.
 */
class ContentGenerationEngine
{
    /**
     * Generate dynamic content based on template and context.
     *
     * @param string $templateContent Template content with placeholders
     * @param array $context Data context
     * @param string $industry Industry type
     * @return string Generated content
     */
    public static function generateContent(string $templateContent, array $context, string $industry = 'general'): string
    {
        // Process industry-specific placeholders
        $content = self::processIndustryPlaceholders($templateContent, $context, $industry);
        
        // Process general placeholders
        $content = self::processGeneralPlaceholders($content, $context);
        
        // Process conditional content
        $content = self::processConditionalContent($content, $context);
        
        // Process loops and iterations
        $content = self::processLoops($content, $context);
        
        return $content;
    }

    /**
     * Process industry-specific placeholders.
     *
     * @param string $content Template content
     * @param array $context Data context
     * @param string $industry Industry type
     * @return string Processed content
     */
    private static function processIndustryPlaceholders(string $content, array $context, string $industry): string
    {
        $industryProcessors = [
            'hospitality' => [self::class, 'processHospitalityPlaceholders'],
            'hotel' => [self::class, 'processHotelPlaceholders'],
            'resort' => [self::class, 'processResortPlaceholders'],
            'wine' => [self::class, 'processWinePlaceholders'],
            'bnb' => [self::class, 'processBnbPlaceholders'],
        ];

        if (isset($industryProcessors[$industry])) {
            $content = call_user_func($industryProcessors[$industry], $content, $context);
        }

        return $content;
    }

    /**
     * Process hospitality industry placeholders.
     *
     * @param string $content Template content
     * @param array $context Data context
     * @return string Processed content
     */
    private static function processHospitalityPlaceholders(string $content, array $context): string
    {
        $placeholders = [
            '{{hospitality.occupancy_rate}}' => self::getOccupancyRate($context),
            '{{hospitality.revenue_per_room}}' => self::getRevenuePerRoom($context),
            '{{hospitality.average_stay_duration}}' => self::getAverageStayDuration($context),
            '{{hospitality.guest_satisfaction}}' => self::getGuestSatisfaction($context),
            '{{hospitality.direct_bookings}}' => self::getDirectBookings($context),
            '{{hospitality.ota_bookings}}' => self::getOtaBookings($context),
            '{{hospitality.seasonal_performance}}' => self::getSeasonalPerformance($context),
            '{{hospitality.amenities_usage}}' => self::getAmenitiesUsage($context),
        ];

        return str_replace(array_keys($placeholders), array_values($placeholders), $content);
    }

    /**
     * Process hotel industry placeholders.
     *
     * @param string $content Template content
     * @param array $context Data context
     * @return string Processed content
     */
    private static function processHotelPlaceholders(string $content, array $context): string
    {
        $placeholders = [
            '{{hotel.room_revenue}}' => self::getRoomRevenue($context),
            '{{hotel.food_beverage_revenue}}' => self::getFoodBeverageRevenue($context),
            '{{hotel.conference_revenue}}' => self::getConferenceRevenue($context),
            '{{hotel.spa_revenue}}' => self::getSpaRevenue($context),
            '{{hotel.business_travelers}}' => self::getBusinessTravelers($context),
            '{{hotel.leisure_travelers}}' => self::getLeisureTravelers($context),
            '{{hotel.group_bookings}}' => self::getGroupBookings($context),
            '{{hotel.loyalty_members}}' => self::getLoyaltyMembers($context),
            '{{hotel.repeat_guests}}' => self::getRepeatGuests($context),
            '{{hotel.ancillary_revenue}}' => self::getAncillaryRevenue($context),
        ];

        return str_replace(array_keys($placeholders), array_values($placeholders), $content);
    }

    /**
     * Process resort industry placeholders.
     *
     * @param string $content Template content
     * @param array $context Data context
     * @return string Processed content
     */
    private static function processResortPlaceholders(string $content, array $context): string
    {
        $placeholders = [
            '{{resort.villa_occupancy}}' => self::getVillaOccupancy($context),
            '{{resort.activity_revenue}}' => self::getActivityRevenue($context),
            '{{resort.wedding_revenue}}' => self::getWeddingRevenue($context),
            '{{resort.golf_revenue}}' => self::getGolfRevenue($context),
            '{{resort.beach_usage}}' => self::getBeachUsage($context),
            '{{resort.spa_treatments}}' => self::getSpaTreatments($context),
            '{{resort.family_packages}}' => self::getFamilyPackages($context),
            '{{resort.honeymoon_packages}}' => self::getHoneymoonPackages($context),
            '{{resort.all_inclusive_revenue}}' => self::getAllInclusiveRevenue($context),
            '{{resort.excursion_bookings}}' => self::getExcursionBookings($context),
        ];

        return str_replace(array_keys($placeholders), array_values($placeholders), $content);
    }

    /**
     * Process wine industry placeholders.
     *
     * @param string $content Template content
     * @param array $context Data context
     * @return string Processed content
     */
    private static function processWinePlaceholders(string $content, array $context): string
    {
        $placeholders = [
            '{{wine.cellar_sales}}' => self::getCellarSales($context),
            '{{wine.tasting_revenue}}' => self::getTastingRevenue($context),
            '{{wine.wine_club_members}}' => self::getWineClubMembers($context),
            '{{wine.vineyard_tours}}' => self::getVineyardTours($context),
            '{{wine.restaurant_revenue}}' => self::getRestaurantRevenue($context),
            '{{wine.wedding_venue_revenue}}' => self::getWeddingVenueRevenue($context),
            '{{wine.retail_sales}}' => self::getRetailSales($context),
            '{{wine.wholesale_sales}}' => self::getWholesaleSales($context),
            '{{wine.export_sales}}' => self::getExportSales($context),
            '{{wine.seasonal_visitors}}' => self::getSeasonalVisitors($context),
            '{{wine.wine_education_classes}}' => self::getWineEducationClasses($context),
            '{{wine.corporate_events}}' => self::getCorporateEvents($context),
        ];

        return str_replace(array_keys($placeholders), array_values($placeholders), $content);
    }

    /**
     * Process B&B industry placeholders.
     *
     * @param string $content Template content
     * @param array $context Data context
     * @return string Processed content
     */
    private static function processBnbPlaceholders(string $content, array $context): string
    {
        $placeholders = [
            '{{bnb.room_occupancy}}' => self::getBnbRoomOccupancy($context),
            '{{bnb.breakfast_revenue}}' => self::getBreakfastRevenue($context),
            '{{bnb.local_experiences}}' => self::getLocalExperiences($context),
            '{{bnb.weekend_bookings}}' => self::getWeekendBookings($context),
            '{{bnb.romantic_packages}}' => self::getRomanticPackages($context),
            '{{bnb.cultural_tours}}' => self::getCulturalTours($context),
            '{{bnb.local_recommendations}}' => self::getLocalRecommendations($context),
            '{{bnb.sustainable_tourism}}' => self::getSustainableTourism($context),
            '{{bnb.homestay_experience}}' => self::getHomestayExperience($context),
            '{{bnb.local_partnerships}}' => self::getLocalPartnerships($context),
        ];

        return str_replace(array_keys($placeholders), array_values($placeholders), $content);
    }

    /**
     * Process general placeholders.
     *
     * @param string $content Template content
     * @param array $context Data context
     * @return string Processed content
     */
    private static function processGeneralPlaceholders(string $content, array $context): string
    {
        $placeholders = [
            '{{client.name}}' => $context['client']['name'] ?? 'Cliente',
            '{{client.logo}}' => $context['client']['logo_url'] ?? '',
            '{{period.start}}' => $context['period']['start'] ?? '',
            '{{period.end}}' => $context['period']['end'] ?? '',
            '{{period.duration}}' => self::getPeriodDuration($context),
            '{{report.date}}' => current_time('d/m/Y'),
            '{{report.generated_at}}' => current_time('H:i'),
        ];

        return str_replace(array_keys($placeholders), array_values($placeholders), $content);
    }

    /**
     * Process conditional content blocks.
     *
     * @param string $content Template content
     * @param array $context Data context
     * @return string Processed content
     */
    private static function processConditionalContent(string $content, array $context): string
    {
        // Process {{if condition}}...{{endif}} blocks
        $content = preg_replace_callback(
            '/\{\{if\s+([^}]+)\}\}(.*?)\{\{endif\}\}/s',
            function ($matches) use ($context) {
                $condition = trim($matches[1]);
                $blockContent = $matches[2];
                
                if (self::evaluateCondition($condition, $context)) {
                    return $blockContent;
                }
                
                return '';
            },
            $content
        );

        return $content;
    }

    /**
     * Process loops and iterations.
     *
     * @param string $content Template content
     * @param array $context Data context
     * @return string Processed content
     */
    private static function processLoops(string $content, array $context): string
    {
        // Process {{foreach array}}...{{endforeach}} blocks
        $content = preg_replace_callback(
            '/\{\{foreach\s+([^}]+)\}\}(.*?)\{\{endforeach\}\}/s',
            function ($matches) use ($context) {
                $arrayPath = trim($matches[1]);
                $blockContent = $matches[2];
                
                $array = self::getNestedValue($context, $arrayPath);
                
                if (!is_array($array)) {
                    return '';
                }
                
                $result = '';
                foreach ($array as $item) {
                    $itemContent = $blockContent;
                    $itemContent = str_replace('{{item}}', $item, $itemContent);
                    $itemContent = str_replace('{{item.key}}', is_array($item) ? $item['key'] ?? '' : '', $itemContent);
                    $itemContent = str_replace('{{item.value}}', is_array($item) ? $item['value'] ?? '' : '', $itemContent);
                    $result .= $itemContent;
                }
                
                return $result;
            },
            $content
        );

        return $content;
    }

    /**
     * Evaluate a condition.
     *
     * @param string $condition Condition to evaluate
     * @param array $context Data context
     * @return bool Condition result
     */
    private static function evaluateCondition(string $condition, array $context): bool
    {
        // Simple condition evaluation
        if (strpos($condition, '>') !== false) {
            [$left, $right] = explode('>', $condition, 2);
            $leftValue = self::getNestedValue($context, trim($left));
            $rightValue = trim($right);
            
            return (float) $leftValue > (float) $rightValue;
        }
        
        if (strpos($condition, '<') !== false) {
            [$left, $right] = explode('<', $condition, 2);
            $leftValue = self::getNestedValue($context, trim($left));
            $rightValue = trim($right);
            
            return (float) $leftValue < (float) $rightValue;
        }
        
        if (strpos($condition, '==') !== false) {
            [$left, $right] = explode('==', $condition, 2);
            $leftValue = self::getNestedValue($context, trim($left));
            $rightValue = trim($right);
            
            return $leftValue == $rightValue;
        }
        
        // Simple existence check
        $value = self::getNestedValue($context, $condition);
        return !empty($value);
    }

    /**
     * Get nested value from context.
     *
     * @param array $context Data context
     * @param string $path Dot notation path
     * @return mixed Value or null
     */
    private static function getNestedValue(array $context, string $path)
    {
        $keys = explode('.', $path);
        $value = $context;
        
        foreach ($keys as $key) {
            if (is_array($value) && array_key_exists($key, $value)) {
                $value = $value[$key];
            } else {
                return null;
            }
        }
        
        return $value;
    }

    // Industry-specific data getters
    private static function getOccupancyRate(array $context): string
    {
        $rate = $context['kpi']['occupancy_rate'] ?? 0;
        return number_format($rate, 1) . '%';
    }

    private static function getRevenuePerRoom(array $context): string
    {
        $revenue = $context['kpi']['revenue_per_room'] ?? 0;
        return '€' . number_format($revenue, 2);
    }

    private static function getAverageStayDuration(array $context): string
    {
        $duration = $context['kpi']['average_stay_duration'] ?? 0;
        return $duration . ' notti';
    }

    private static function getGuestSatisfaction(array $context): string
    {
        $satisfaction = $context['kpi']['guest_satisfaction'] ?? 0;
        return number_format($satisfaction, 1) . '/10';
    }

    private static function getDirectBookings(array $context): string
    {
        $bookings = $context['kpi']['direct_bookings'] ?? 0;
        return number_format($bookings);
    }

    private static function getOtaBookings(array $context): string
    {
        $bookings = $context['kpi']['ota_bookings'] ?? 0;
        return number_format($bookings);
    }

    private static function getSeasonalPerformance(array $context): string
    {
        $performance = $context['kpi']['seasonal_performance'] ?? 0;
        return number_format($performance, 1) . '%';
    }

    private static function getAmenitiesUsage(array $context): string
    {
        $usage = $context['kpi']['amenities_usage'] ?? 0;
        return number_format($usage, 1) . '%';
    }

    // Hotel-specific getters
    private static function getRoomRevenue(array $context): string
    {
        $revenue = $context['kpi']['room_revenue'] ?? 0;
        return '€' . number_format($revenue, 2);
    }

    private static function getFoodBeverageRevenue(array $context): string
    {
        $revenue = $context['kpi']['food_beverage_revenue'] ?? 0;
        return '€' . number_format($revenue, 2);
    }

    private static function getConferenceRevenue(array $context): string
    {
        $revenue = $context['kpi']['conference_revenue'] ?? 0;
        return '€' . number_format($revenue, 2);
    }

    private static function getSpaRevenue(array $context): string
    {
        $revenue = $context['kpi']['spa_revenue'] ?? 0;
        return '€' . number_format($revenue, 2);
    }

    private static function getBusinessTravelers(array $context): string
    {
        $travelers = $context['kpi']['business_travelers'] ?? 0;
        return number_format($travelers);
    }

    private static function getLeisureTravelers(array $context): string
    {
        $travelers = $context['kpi']['leisure_travelers'] ?? 0;
        return number_format($travelers);
    }

    private static function getGroupBookings(array $context): string
    {
        $bookings = $context['kpi']['group_bookings'] ?? 0;
        return number_format($bookings);
    }

    private static function getLoyaltyMembers(array $context): string
    {
        $members = $context['kpi']['loyalty_members'] ?? 0;
        return number_format($members);
    }

    private static function getRepeatGuests(array $context): string
    {
        $guests = $context['kpi']['repeat_guests'] ?? 0;
        return number_format($guests);
    }

    private static function getAncillaryRevenue(array $context): string
    {
        $revenue = $context['kpi']['ancillary_revenue'] ?? 0;
        return '€' . number_format($revenue, 2);
    }

    // Resort-specific getters
    private static function getVillaOccupancy(array $context): string
    {
        $occupancy = $context['kpi']['villa_occupancy'] ?? 0;
        return number_format($occupancy, 1) . '%';
    }

    private static function getActivityRevenue(array $context): string
    {
        $revenue = $context['kpi']['activity_revenue'] ?? 0;
        return '€' . number_format($revenue, 2);
    }

    private static function getWeddingRevenue(array $context): string
    {
        $revenue = $context['kpi']['wedding_revenue'] ?? 0;
        return '€' . number_format($revenue, 2);
    }

    private static function getGolfRevenue(array $context): string
    {
        $revenue = $context['kpi']['golf_revenue'] ?? 0;
        return '€' . number_format($revenue, 2);
    }

    private static function getBeachUsage(array $context): string
    {
        $usage = $context['kpi']['beach_usage'] ?? 0;
        return number_format($usage, 1) . '%';
    }

    private static function getSpaTreatments(array $context): string
    {
        $treatments = $context['kpi']['spa_treatments'] ?? 0;
        return number_format($treatments);
    }

    private static function getFamilyPackages(array $context): string
    {
        $packages = $context['kpi']['family_packages'] ?? 0;
        return number_format($packages);
    }

    private static function getHoneymoonPackages(array $context): string
    {
        $packages = $context['kpi']['honeymoon_packages'] ?? 0;
        return number_format($packages);
    }

    private static function getAllInclusiveRevenue(array $context): string
    {
        $revenue = $context['kpi']['all_inclusive_revenue'] ?? 0;
        return '€' . number_format($revenue, 2);
    }

    private static function getExcursionBookings(array $context): string
    {
        $bookings = $context['kpi']['excursion_bookings'] ?? 0;
        return number_format($bookings);
    }

    // Wine industry getters
    private static function getCellarSales(array $context): string
    {
        $sales = $context['kpi']['cellar_sales'] ?? 0;
        return '€' . number_format($sales, 2);
    }

    private static function getTastingRevenue(array $context): string
    {
        $revenue = $context['kpi']['tasting_revenue'] ?? 0;
        return '€' . number_format($revenue, 2);
    }

    private static function getWineClubMembers(array $context): string
    {
        $members = $context['kpi']['wine_club_members'] ?? 0;
        return number_format($members);
    }

    private static function getVineyardTours(array $context): string
    {
        $tours = $context['kpi']['vineyard_tours'] ?? 0;
        return number_format($tours);
    }

    private static function getRestaurantRevenue(array $context): string
    {
        $revenue = $context['kpi']['restaurant_revenue'] ?? 0;
        return '€' . number_format($revenue, 2);
    }

    private static function getWeddingVenueRevenue(array $context): string
    {
        $revenue = $context['kpi']['wedding_venue_revenue'] ?? 0;
        return '€' . number_format($revenue, 2);
    }

    private static function getRetailSales(array $context): string
    {
        $sales = $context['kpi']['retail_sales'] ?? 0;
        return '€' . number_format($sales, 2);
    }

    private static function getWholesaleSales(array $context): string
    {
        $sales = $context['kpi']['wholesale_sales'] ?? 0;
        return '€' . number_format($sales, 2);
    }

    private static function getExportSales(array $context): string
    {
        $sales = $context['kpi']['export_sales'] ?? 0;
        return '€' . number_format($sales, 2);
    }

    private static function getSeasonalVisitors(array $context): string
    {
        $visitors = $context['kpi']['seasonal_visitors'] ?? 0;
        return number_format($visitors);
    }

    private static function getWineEducationClasses(array $context): string
    {
        $classes = $context['kpi']['wine_education_classes'] ?? 0;
        return number_format($classes);
    }

    private static function getCorporateEvents(array $context): string
    {
        $events = $context['kpi']['corporate_events'] ?? 0;
        return number_format($events);
    }

    // B&B specific getters
    private static function getBnbRoomOccupancy(array $context): string
    {
        $occupancy = $context['kpi']['bnb_room_occupancy'] ?? 0;
        return number_format($occupancy, 1) . '%';
    }

    private static function getBreakfastRevenue(array $context): string
    {
        $revenue = $context['kpi']['breakfast_revenue'] ?? 0;
        return '€' . number_format($revenue, 2);
    }

    private static function getLocalExperiences(array $context): string
    {
        $experiences = $context['kpi']['local_experiences'] ?? 0;
        return number_format($experiences);
    }

    private static function getWeekendBookings(array $context): string
    {
        $bookings = $context['kpi']['weekend_bookings'] ?? 0;
        return number_format($bookings);
    }

    private static function getRomanticPackages(array $context): string
    {
        $packages = $context['kpi']['romantic_packages'] ?? 0;
        return number_format($packages);
    }

    private static function getCulturalTours(array $context): string
    {
        $tours = $context['kpi']['cultural_tours'] ?? 0;
        return number_format($tours);
    }

    private static function getLocalRecommendations(array $context): string
    {
        $recommendations = $context['kpi']['local_recommendations'] ?? 0;
        return number_format($recommendations);
    }

    private static function getSustainableTourism(array $context): string
    {
        $sustainable = $context['kpi']['sustainable_tourism'] ?? 0;
        return number_format($sustainable, 1) . '%';
    }

    private static function getHomestayExperience(array $context): string
    {
        $experience = $context['kpi']['homestay_experience'] ?? 0;
        return number_format($experience, 1) . '/10';
    }

    private static function getLocalPartnerships(array $context): string
    {
        $partnerships = $context['kpi']['local_partnerships'] ?? 0;
        return number_format($partnerships);
    }

    private static function getPeriodDuration(array $context): string
    {
        $start = $context['period']['start'] ?? '';
        $end = $context['period']['end'] ?? '';
        
        if ($start && $end) {
            $startDate = new \DateTime($start);
            $endDate = new \DateTime($end);
            $diff = $startDate->diff($endDate);
            return $diff->days . ' giorni';
        }
        
        return '';
    }
}
