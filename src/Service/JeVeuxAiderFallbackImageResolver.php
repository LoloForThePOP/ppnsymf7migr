<?php

namespace App\Service;

final class JeVeuxAiderFallbackImageResolver
{
    /**
     * @var array<string, string>
     */
    private const CATEGORY_TO_FOLDER = [
        'animals' => 'general_animals',
        'education_or_inform' => 'education_or_inform',
        'inform' => 'education_or_inform',
        'environment' => 'nature',
        'health' => 'health',
        'sports' => 'sport',
        'social_inclusion' => 'social_inclusion',
        'services' => 'community_support',
        'humane' => 'community_support',
        'food' => 'volunteer_distribution',
    ];

    /**
     * Score rules ordered from most specific to broadest.
     *
     * @var array<int, array{folder:string,keywords:array<int,string>,weight:int}>
     */
    private const KEYWORD_RULES = [
        ['folder' => 'cats', 'weight' => 10, 'keywords' => [
            'chat', 'chats', 'chaton', 'chatons', 'felin', 'felins', 'minou', 'gouttiere',
            'protection des chats', 'nourrissage des chats', 'sterilisation des chats',
        ]],
        ['folder' => 'dogs', 'weight' => 10, 'keywords' => [
            'chien', 'chiens', 'chiot', 'chiots', 'canin', 'canins', 'toutou',
            'education canine', 'promenade de chiens', 'refuge canin',
        ]],
        ['folder' => 'sea', 'weight' => 9, 'keywords' => [
            'mer', 'littoral', 'ocean', 'plage', 'marin', 'maritime', 'nautique', 'cote',
            'milieu marin', 'fonds marins', 'faune marine', 'bord de mer', 'port', 'voile',
            'pollution marine', 'ramassage dechets plage',
        ]],
        ['folder' => 'athletism', 'weight' => 9, 'keywords' => [
            'athletisme', 'course a pied', 'running', 'trail', 'cross', 'jogging', 'marathon', 'semi marathon',
            '10 km', '5 km', 'foulee', 'piste d athletisme',
        ]],
        ['folder' => 'stadium', 'weight' => 9, 'keywords' => [
            'stade', 'gymnase', 'terrain de sport', 'infrastructure sportive', 'complexe sportif', 'tribune',
            'terrain', 'club house', 'infrastructure de stade',
        ]],
        ['folder' => 'volunteer_distribution', 'weight' => 9, 'keywords' => [
            'distribution alimentaire', 'banque alimentaire', 'aide alimentaire', 'colis alimentaire',
            'distribution de vetements', 'vestiaire solidaire', 'maraude de distribution',
            'collecte alimentaire', 'collecte de dons', 'redistribution', 'repas chauds', 'epicerie solidaire',
            'restos du coeur', 'denrees', 'colis solidaires', 'aide materielle',
            'distribution de repas', 'distribution de colis', 'distribution de produits d hygiene',
        ]],
        ['folder' => 'homeless', 'weight' => 8, 'keywords' => [
            'sans abri', 'sans domicile', 'sdf', 'hebergement d urgence', 'accueil de nuit', 'mise a l abri', 'rue',
            'maraude', 'personnes a la rue', 'centre d hebergement', '115',
        ]],
        ['folder' => 'legal_advice', 'weight' => 8, 'keywords' => [
            'juridique', 'droit', 'justice', 'avocat', 'mediation', 'acces au droit',
            'aide juridictionnelle', 'defense des droits', 'conseil juridique',
            'permanence juridique', 'information juridique', 'defenseur des droits',
        ]],
        ['folder' => 'home_care', 'weight' => 8, 'keywords' => [
            'a domicile', 'maintien a domicile', 'visite a domicile', 'aide au quotidien',
            'accompagnement a domicile', 'personnes agees a domicile', 'portage de repas',
            'courses a domicile', 'presence a domicile', 'aide aux seniors',
        ]],
        ['folder' => 'talk_and_support', 'weight' => 8, 'keywords' => [
            'ecoute', 'ligne d ecoute', 'soutien psychologique', 'accompagnement psychologique',
            'groupe de parole', 'sante mentale', 'prevention suicide', 'aidants',
            'soutien moral', 'ecoute telephonique', 'orientation psychologique',
        ]],
        ['folder' => 'loneliness', 'weight' => 7, 'keywords' => [
            'isolement', 'solitude', 'rompre l isolement', 'visites de convivialite',
            'visites amicales', 'lien social', 'personnes isolees',
            'personnes seules', 'convivialite', 'visites de courtoisie',
        ]],
        ['folder' => 'health', 'weight' => 7, 'keywords' => [
            'sante', 'soin', 'medical', 'medecin', 'infirmier', 'hopital',
            'secourisme', 'premiers secours', 'handicap', 'maladie', 'addiction',
            'don du sang', 'prevention sante', 'sante publique', 'croix rouge',
        ]],
        ['folder' => 'education_or_inform', 'weight' => 7, 'keywords' => [
            'education', 'scolaire', 'ecole', 'college', 'lycee', 'universite',
            'formation', 'sensibilisation', 'alphabetisation', 'soutien scolaire',
            'information', 'pedagogique', 'orientation',
            'cours de francais', 'f l e', 'illettrisme', 'accompagnement scolaire',
        ]],
        ['folder' => 'administrative', 'weight' => 7, 'keywords' => [
            'administratif', 'demarches', 'acces aux droits', 'paperasse', 'documents',
            'formulaires', 'aide administrative', 'france services',
            'caf', 'cpam', 'france travail', 'pole emploi', 'titre de sejour', 'renouvellement papiers',
        ]],
        ['folder' => 'coaching_guidance', 'weight' => 7, 'keywords' => [
            'coaching', 'mentorat', 'mentor', 'orientation professionnelle',
            'insertion pro', 'recherche d emploi', 'cv', 'lettre de motivation',
            'preparation entretien', 'bilan de competences',
        ]],
        ['folder' => 'citizenship', 'weight' => 6, 'keywords' => [
            'citoyen', 'citoyennete', 'engagement civique', 'civisme', 'republique',
            'participation citoyenne', 'conseil citoyen',
        ]],
        ['folder' => 'craft_activities', 'weight' => 6, 'keywords' => [
            'atelier manuel', 'travaux manuels', 'artisanat', 'bricolage', 'couture',
            'tricot', 'ceramique', 'loisirs creatifs', 'activites manuelles',
        ]],
        ['folder' => 'social_inclusion', 'weight' => 6, 'keywords' => [
            'insertion', 'inclusion', 'exclusion', 'precarite', 'integration',
            'insertion professionnelle', 'insertion sociale', 'accompagnement social',
            'retour a l emploi', 'migrant', 'refugie',
            'allophone', 'demandeur d asile', 'quartier prioritaire',
        ]],
        ['folder' => 'nature', 'weight' => 6, 'keywords' => [
            'nature', 'environnement', 'ecologie', 'biodiversite', 'recyclage',
            'dechets', 'nettoyage nature', 'climat', 'transition ecologique',
            'protection de la nature', 'faune sauvage', 'foret', 'jardin partage',
            'jardinage', 'compost', 'reemploi', 'renaturation',
        ]],
        ['folder' => 'general_animals', 'weight' => 6, 'keywords' => [
            'animal', 'animaux', 'protection animale', 'refuge', 'adoption',
            'bien etre animal', 'faune', 'spa', 'soins animaliers', 'animalerie solidaire',
        ]],
        ['folder' => 'sport', 'weight' => 5, 'keywords' => [
            'sport', 'activite physique', 'club sportif', 'entrainement', 'competition',
            'football', 'basket', 'rugby', 'natation', 'handball', 'volley',
            'sport sante', 'initiation sportive', 'tournoi',
        ]],
        ['folder' => 'community_support', 'weight' => 4, 'keywords' => [
            'solidarite', 'entraide', 'benevolat', 'quartier', 'communaute',
            'animation locale', 'proximite', 'vie associative', 'aide de proximite',
            'actions solidaires', 'mobilisation citoyenne',
        ]],
    ];

    /**
     * @param array<string, mixed> $aiPayload
     * @param array<string, mixed>|null $structuredData
     */
    public function resolve(array $aiPayload, ?array $structuredData = null): string
    {
        $scores = [];

        // Structured domaines are the most reliable deterministic source on JeVeuxAider.
        foreach ($this->extractStructuredDomainTokens($structuredData) as $token) {
            $this->scoreByDomainToken($scores, $token);
        }

        // AI categories stay useful as a secondary signal.
        $categories = $aiPayload['categories'] ?? [];
        if (is_array($categories)) {
            foreach ($categories as $category) {
                if (!is_string($category)) {
                    continue;
                }
                $key = $this->normalize($category);
                if (isset(self::CATEGORY_TO_FOLDER[$key])) {
                    $folder = self::CATEGORY_TO_FOLDER[$key];
                    $scores[$folder] = ($scores[$folder] ?? 0) + 8;
                }
            }
        }

        $corpus = $this->buildCorpus($aiPayload, $structuredData);
        foreach (self::KEYWORD_RULES as $rule) {
            $hits = 0;
            foreach ($rule['keywords'] as $keyword) {
                $needle = $this->normalize($keyword);
                if ($needle === '' || !$this->containsKeyword($corpus, $needle)) {
                    continue;
                }
                $hits++;
            }

            if ($hits > 0) {
                $folder = $rule['folder'];
                $scores[$folder] = ($scores[$folder] ?? 0) + ($hits * $rule['weight']);
            }
        }

        if ($scores === []) {
            return 'fallback';
        }

        arsort($scores);
        $best = array_key_first($scores);

        return is_string($best) && $best !== '' ? $best : 'fallback';
    }

    /**
     * @param array<string, int> $scores
     */
    private function scoreByDomainToken(array &$scores, string $token): void
    {
        if ($token === '') {
            return;
        }

        $add = function (string $folder, int $weight) use (&$scores): void {
            $scores[$folder] = ($scores[$folder] ?? 0) + $weight;
        };

        if (str_contains($token, 'sante') || str_contains($token, 'secours')) {
            $add('health', 14);
        }
        if (str_contains($token, 'education') || str_contains($token, 'formation') || str_contains($token, 'information')) {
            $add('education_or_inform', 14);
        }
        if (str_contains($token, 'environnement') || str_contains($token, 'nature') || str_contains($token, 'biodiversite')) {
            $add('nature', 14);
        }
        if (str_contains($token, 'mer') || str_contains($token, 'littoral') || str_contains($token, 'marin')) {
            $add('sea', 14);
        }
        if (str_contains($token, 'sport')) {
            $add('sport', 14);
        }
        if (str_contains($token, 'athlet') || str_contains($token, 'running')) {
            $add('athletism', 14);
        }
        if (str_contains($token, 'stade') || str_contains($token, 'gymnase') || str_contains($token, 'terrain')) {
            $add('stadium', 13);
        }
        if (str_contains($token, 'animal')) {
            $add('general_animals', 14);
        }
        if (str_contains($token, 'chat')) {
            $add('cats', 14);
        }
        if (str_contains($token, 'chien') || str_contains($token, 'canin')) {
            $add('dogs', 14);
        }
        if (str_contains($token, 'citoyen') || str_contains($token, 'civique')) {
            $add('citizenship', 12);
        }
        if (str_contains($token, 'isolement') || str_contains($token, 'solitude')) {
            $add('loneliness', 12);
        }
        if (str_contains($token, 'insertion') || str_contains($token, 'inclusion') || str_contains($token, 'solidarite')) {
            $add('social_inclusion', 11);
        }
        if (str_contains($token, 'distribution') || str_contains($token, 'alimentaire') || str_contains($token, 'colis')) {
            $add('volunteer_distribution', 12);
        }
        if (str_contains($token, 'maraude') || str_contains($token, 'sans abri') || str_contains($token, 'sdf')) {
            $add('homeless', 12);
        }
        if (str_contains($token, 'ecoute') || str_contains($token, 'soutien psychologique')) {
            $add('talk_and_support', 11);
        }
        if (str_contains($token, 'isolement') || str_contains($token, 'solitude')) {
            $add('loneliness', 11);
        }
        if (str_contains($token, 'administratif') || str_contains($token, 'acces aux droits')) {
            $add('administrative', 12);
        }
        if (str_contains($token, 'juridique') || str_contains($token, 'droit')) {
            $add('legal_advice', 12);
        }
        if (str_contains($token, 'domicile') || str_contains($token, 'personnes agees')) {
            $add('home_care', 11);
        }
        if (str_contains($token, 'coaching') || str_contains($token, 'mentorat') || str_contains($token, 'orientation pro')) {
            $add('coaching_guidance', 11);
        }
    }

    /**
     * @param array<string, mixed>|null $structuredData
     * @return array<int, string>
     */
    private function extractStructuredDomainTokens(?array $structuredData): array
    {
        if ($structuredData === null) {
            return [];
        }

        $domaines = $structuredData['domaines'] ?? null;
        if (!is_array($domaines)) {
            return [];
        }

        $tokens = [];
        foreach ($domaines as $domain) {
            if (!is_array($domain)) {
                continue;
            }
            foreach (['slug', 'name', 'title'] as $key) {
                $value = $domain[$key] ?? null;
                if (!is_string($value) || trim($value) === '') {
                    continue;
                }
                $tokens[] = $this->normalize($value);
            }
        }

        return $tokens;
    }

    /**
     * @param array<string, mixed> $aiPayload
     * @param array<string, mixed>|null $structuredData
     */
    private function buildCorpus(array $aiPayload, ?array $structuredData): string
    {
        $parts = [];

        foreach (['title', 'goal', 'description_html'] as $key) {
            $value = $aiPayload[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $parts[] = $value;
            }
        }

        foreach (['keywords'] as $key) {
            $value = $aiPayload[$key] ?? null;
            if (is_array($value)) {
                foreach ($value as $v) {
                    if (is_string($v) && trim($v) !== '') {
                        $parts[] = $v;
                    }
                }
            }
        }

        if ($structuredData !== null) {
            foreach (['name', 'description'] as $key) {
                $value = $structuredData[$key] ?? null;
                if (is_string($value) && trim($value) !== '') {
                    $parts[] = $value;
                }
            }

            $publics = $structuredData['publics_beneficiaires'] ?? null;
            if (is_array($publics)) {
                foreach ($publics as $public) {
                    if (is_string($public) && trim($public) !== '') {
                        $parts[] = $public;
                    }
                }
            }
        }

        return $this->normalize(implode(' ', $parts));
    }

    private function containsKeyword(string $corpus, string $needle): bool
    {
        if ($needle === '') {
            return false;
        }

        return str_contains(' ' . $corpus . ' ', ' ' . $needle . ' ');
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $translit = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($translit) && $translit !== '') {
            $value = $translit;
        }
        $value = preg_replace('/[^a-z0-9]+/i', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }
}
