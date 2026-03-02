<?php

namespace App\Service;

final class MealPresetService
{
    /**
     * Retourne des presets "repas précis" par objectif.
     * Chaque preset contient:
     * - title: nom du repas
     * - typeRepasValue: valeur à injecter dans form.typeRepas (doit exister dans RepasType choices)
     * - description: texte à mettre dans description
     * - queries: mots clés pour rechercher sur OpenFoodFacts
     */
    public function presetsForObjectif(?string $objectif): array
    {
        $o = mb_strtolower(trim((string) $objectif));

        // Perte de poids
        if ($this->has($o, ['perte', 'maigr', 'minc', 'cut'])) {
            return [
                [
                    'category' => 'Petit-déjeuner',
                    'title' => 'Yaourt grec 0% + flocons d’avoine',
                    'typeRepasValue' => 'Repas protéiné léger',
                    'description' => 'Yaourt grec 0% + flocons d’avoine (objectif perte de poids).',
                    'queries' => ['yaourt grec 0%', 'flocons d avoine', 'fromage blanc 0%'],
                ],
                [
                    'category' => 'Déjeuner',
                    'title' => 'Salade + thon naturel',
                    'typeRepasValue' => 'Repas riche en fibres',
                    'description' => 'Salade verte + thon naturel + légumes (faible calories, riche en protéines).',
                    'queries' => ['thon naturel', 'salade', 'crudites'],
                ],
                [
                    'category' => 'Dîner',
                    'title' => 'Blanc de poulet + brocoli',
                    'typeRepasValue' => 'Repas hypocalorique',
                    'description' => 'Blanc de poulet + brocoli vapeur (objectif perte de poids).',
                    'queries' => ['blanc de poulet', 'brocoli', 'legumes vapeur'],
                ],
                [
                    'category' => 'Collation',
                    'title' => 'Pomme / fruits + amandes',
                    'typeRepasValue' => 'Collation légère',
                    'description' => 'Fruits (pomme) + petite portion d’amandes (collation légère).',
                    'queries' => ['pomme', 'amandes', 'noix'],
                ],
            ];
        }

        // Prise de masse
        if ($this->has($o, ['prise', 'masse', 'bulk', 'muscle'])) {
            return [
                [
                    'category' => 'Petit-déjeuner',
                    'title' => 'Oeufs + avoine + lait entier',
                    'typeRepasValue' => 'Repas hypercalorique',
                    'description' => 'Oeufs + flocons d’avoine + lait entier (objectif prise de masse).',
                    'queries' => ['oeufs', 'flocons d avoine', 'lait entier'],
                ],
                [
                    'category' => 'Déjeuner',
                    'title' => 'Riz + poulet',
                    'typeRepasValue' => 'Recharge glucidique',
                    'description' => 'Riz + poulet (glucides + protéines pour prise de masse).',
                    'queries' => ['riz', 'poulet', 'pates'],
                ],
                [
                    'category' => 'Collation',
                    'title' => 'Beurre de cacahuète + banane',
                    'typeRepasValue' => 'Collation énergétique',
                    'description' => 'Beurre de cacahuète + banane (collation énergétique).',
                    'queries' => ['beurre de cacahuete', 'banane', 'barre protéinée'],
                ],
                [
                    'category' => 'Post-training',
                    'title' => 'Whey (optionnel) + banane',
                    'typeRepasValue' => 'Shake / supplément (optionnel)',
                    'description' => 'Whey + banane (optionnel) après entraînement.',
                    'queries' => ['whey', 'protein', 'shake'],
                ],
            ];
        }

        // Détox
        if ($this->has($o, ['detox', 'détox'])) {
            return [
                [
                    'category' => 'Matin',
                    'title' => 'Smoothie pomme + citron',
                    'typeRepasValue' => 'Jus / smoothie',
                    'description' => 'Smoothie pomme + citron (détox).',
                    'queries' => ['jus pomme', 'citron', 'smoothie'],
                ],
                [
                    'category' => 'Midi',
                    'title' => 'Soupe de légumes',
                    'typeRepasValue' => 'Soupe / bouillon',
                    'description' => 'Soupe de légumes (détox).',
                    'queries' => ['soupe legumes', 'bouillon legumes'],
                ],
                [
                    'category' => 'Soir',
                    'title' => 'Fruits + crudités',
                    'typeRepasValue' => 'Fruits / crudités',
                    'description' => 'Fruits + crudités (détox).',
                    'queries' => ['crudites', 'fruits'],
                ],
                [
                    'category' => 'Hydratation',
                    'title' => 'Thé vert / infusion',
                    'typeRepasValue' => 'Hydratation',
                    'description' => 'Hydratation + infusion / thé vert.',
                    'queries' => ['the vert', 'infusion'],
                ],
            ];
        }

        // Maintien
        if ($this->has($o, ['maintien'])) {
            return [
                [
                    'category' => 'Petit-déjeuner',
                    'title' => 'Yaourt + fruits',
                    'typeRepasValue' => 'Repas équilibré',
                    'description' => 'Yaourt + fruits (maintien).',
                    'queries' => ['yaourt', 'fruits'],
                ],
                [
                    'category' => 'Déjeuner',
                    'title' => 'Poulet + légumes',
                    'typeRepasValue' => 'Repas riche en légumes',
                    'description' => 'Poulet + légumes (maintien).',
                    'queries' => ['poulet', 'legumes'],
                ],
                [
                    'category' => 'Dîner',
                    'title' => 'Riz complet + thon',
                    'typeRepasValue' => 'Repas riche en protéines',
                    'description' => 'Riz complet + thon (maintien).',
                    'queries' => ['riz complet', 'thon'],
                ],
                [
                    'category' => 'Collation',
                    'title' => 'Snack contrôlé',
                    'typeRepasValue' => 'Snack contrôlé',
                    'description' => 'Snack contrôlé (maintien).',
                    'queries' => ['amandes', 'noix', 'fruit'],
                ],
            ];
        }

        // Régime spécial (générique)
        if ($this->has($o, ['régime', 'special', 'spécial', 'gluten', 'lactose', 'vegan'])) {
            return [
                [
                    'category' => 'Choix',
                    'title' => 'Sans gluten',
                    'typeRepasValue' => 'Sans gluten',
                    'description' => 'Choix sans gluten.',
                    'queries' => ['sans gluten'],
                ],
                [
                    'category' => 'Choix',
                    'title' => 'Sans lactose',
                    'typeRepasValue' => 'Sans lactose',
                    'description' => 'Choix sans lactose.',
                    'queries' => ['sans lactose'],
                ],
                [
                    'category' => 'Choix',
                    'title' => 'Vegan',
                    'typeRepasValue' => 'Végétarien / Vegan',
                    'description' => 'Choix vegan.',
                    'queries' => ['vegan', 'vegetal'],
                ],
            ];
        }

        // défaut
        return [
            [
                'category' => 'Suggestion',
                'title' => 'Repas équilibré (générique)',
                'typeRepasValue' => 'Repas équilibré',
                'description' => 'Repas équilibré.',
                'queries' => ['yaourt', 'poulet', 'riz'],
            ],
        ];
    }

    private function has(string $txt, array $needles): bool
    {
        foreach ($needles as $n) {
            $n = mb_strtolower(trim($n));
            if ($n !== '' && str_contains($txt, $n)) return true;
        }
        return false;
    }
}
