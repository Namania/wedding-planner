<?php

namespace Database\Factories;

use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    // Quelques titres plausibles par catégorie, pour des données de démo qui
    // ont du sens plutôt que des titres génériques.
    private const TITLES_BY_CATEGORY = [
        'administratif' => ['Réserver la mairie', 'Demander les autorisations pour le lieu', 'Souscrire une assurance annulation', 'Mettre à jour le livret de famille'],
        'prestataires' => ['Signer le contrat avec le traiteur', 'Relancer le photographe', 'Comparer les devis DJ', 'Confirmer le lieu de réception'],
        'tenues' => ['Essayer la robe de mariée', 'Choisir le costume du marié', 'Prendre rendez-vous pour les retouches', 'Acheter les chaussures'],
        'deco' => ['Choisir le thème de décoration', 'Commander les centres de table', "Réserver l'arche de cérémonie", 'Choisir la vaisselle'],
        'invitations' => ['Envoyer les faire-part', "Créer la liste d'invités", 'Relancer les invités sans réponse', 'Imprimer le plan de table'],
        'beaute' => ['Essai coiffure', 'Essai maquillage', "Réserver l'institut pour le jour J", "Manucure d'essai"],
        'logistique' => ['Réserver les hébergements pour les invités', 'Organiser le transport des invités', 'Prévoir le rétroplanning du jour J', 'Vérifier la météo'],
        'autre' => ['Préparer les alliances', 'Rédiger les voeux', 'Préparer une playlist', 'Choisir un cadeau pour les témoins'],
    ];

    public function definition(): array
    {
        $category = fake()->randomElement(Task::CATEGORIES);

        return [
            'title' => fake()->randomElement(self::TITLES_BY_CATEGORY[$category]),
            'category' => $category,
            'status' => fake()->randomElement([...Task::STATUSES, ...Task::STATUSES, 'todo']),
            'due_date' => fake()->optional(0.7)->dateTimeBetween('now', '+18 months'),
        ];
    }
}
