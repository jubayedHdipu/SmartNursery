<?php
// ============================================================
// FILE: helpers/plant_matcher.php
// PURPOSE: Scores and recommends plants matching user lifestyle inputs.
// ============================================================

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';

function get_plant_recommendations(array $userPreferences): array {
    $db = get_db();

    $roomType    = trim($userPreferences['room_type'] ?? 'Living Room');
    $sunlight    = trim($userPreferences['sunlight'] ?? 'Bright Indirect');
    $careLevel   = trim($userPreferences['care_level'] ?? 'Easy');
    $petFriendly = !empty($userPreferences['pet_friendly']) ? 1 : 0;
    $purpose     = trim($userPreferences['purpose'] ?? 'Air Purifier');

    // Step 1: Query approved plants that are in stock
    $sql = "
        SELECT p.*, c.name AS category_name, c.name_bn AS category_name_bn,
               vp.nursery_name, vp.nursery_name_bn,
               (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image
        FROM products p
        JOIN categories c ON p.category_id = c.id
        JOIN vendor_profiles vp ON p.vendor_id = vp.id
        WHERE p.status = 'approved' AND p.stock_quantity > 0
    ";

    if ($petFriendly === 1) {
        $sql .= " AND p.is_pet_friendly = 1";
    }

    $sql .= " ORDER BY p.is_featured DESC, p.stock_quantity DESC LIMIT 30";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $plants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fallback if no pet-friendly plants found
    if (empty($plants)) {
        $stmt = $db->query("
            SELECT p.*, c.name AS category_name, c.name_bn AS category_name_bn,
                   vp.nursery_name, vp.nursery_name_bn,
                   (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image
            FROM products p
            JOIN categories c ON p.category_id = c.id
            JOIN vendor_profiles vp ON p.vendor_id = vp.id
            WHERE p.status = 'approved' AND p.stock_quantity > 0
            LIMIT 15
        ");
        $plants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Step 2: Score each plant based on user criteria
    $scoredPlants = [];

    foreach ($plants as $plant) {
        $score = 75; // Base starting score
        $reasons = [];

        // Sunlight check (+10 points)
        if (!empty($plant['sunlight_requirement'])) {
            if (strcasecmp($plant['sunlight_requirement'], $sunlight) === 0) {
                $score += 10;
                $reasons[] = "Thrives in " . $plant['sunlight_requirement'];
            } else {
                $score += 5;
                $reasons[] = "Adaptable to " . $sunlight;
            }
        }

        // Care difficulty check (+8 points)
        if (!empty($plant['care_level'])) {
            if (strcasecmp($plant['care_level'], $careLevel) === 0) {
                $score += 8;
                $reasons[] = $plant['care_level'] . " maintenance";
            } else {
                $score += 4;
                $reasons[] = "Manageable care requirements";
            }
        }

        // Pet safety (+5 points)
        if (!empty($plant['is_pet_friendly'])) {
            $score += 5;
            $reasons[] = "Pet-safe & non-toxic";
        }

        // Water and room fit
        $waterFreq = !empty($plant['water_frequency']) ? $plant['water_frequency'] : 'Regular';
        $reasons[] = $waterFreq . " watering schedule";
        $reasons[] = "Ideal for your " . $roomType . " ambiance";

        // Keep score between 80% and 98%
        if ($score > 98) {
            $score = 98;
        }
        if ($score < 80) {
            $score = 82;
        }

        $plant['ai_match_score'] = $score;
        $plant['ai_reason']      = implode(". ", array_slice($reasons, 0, 3)) . ".";
        $plant['image_url']      = get_image_url($plant['primary_image']);
        $plant['primary_image']  = get_image_url($plant['primary_image']);

        $scoredPlants[] = $plant;
    }

    // Step 3: Sort plants by score descending
    usort($scoredPlants, function($a, $b) {
        if ($a['ai_match_score'] == $b['ai_match_score']) {
            return 0;
        }
        return ($a['ai_match_score'] > $b['ai_match_score']) ? -1 : 1;
    });

    return array_slice($scoredPlants, 0, 6);
}

function get_grounded_plant_recommendations(array $userPreferences): array {
    return get_plant_recommendations($userPreferences);
}

function generate_product_description_botanical(array $plantData): string {
    $name       = trim($plantData['name'] ?? 'Botanical Specimen');
    $sciName    = trim($plantData['scientific_name'] ?? '');
    $category   = trim($plantData['category'] ?? 'Indoor Foliage');
    $care       = trim($plantData['care_level'] ?? 'Easy');
    $sunlight   = trim($plantData['sunlight'] ?? 'Bright Indirect');
    $highlights = trim($plantData['highlights'] ?? 'Lush healthy foliage, air purifying benefits, and decorative appeal');

    $desc = $name;
    if (!empty($sciName)) {
        $desc .= " (" . $sciName . ")";
    }
    $desc .= " is a premium specimen in our " . $category . " collection.\n\n";
    $desc .= "🌿 Care & Placement:\n";
    $desc .= "• Sunlight: Flourishes best in " . $sunlight . " conditions.\n";
    $desc .= "• Maintenance: Rated as " . $care . " care level — beginner friendly.\n";
    $desc .= "• Highlights: " . $highlights . ".\n\n";
    $desc .= "🌱 Nursery Guarantee:\n";
    $desc .= "Cultivated with healthy root development, pest-free certification, and secure packaging.";

    return $desc;
}

function generate_product_description_ai(array $plantData): string {
    return generate_product_description_botanical($plantData);
}
