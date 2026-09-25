<?php

namespace App\Service;

/**
 * Builder pour configurer l'affichage des détails d'une entité en modale
 *
 * Exemple:
 * $detail = $builder
 *     ->setEntity(TypeDiplome::class)
 *     ->addField('libelle', ['label' => 'Libellé', 'type' => 'text'])
 *     ->addField('hasMemoire', ['label' => 'Mémoire ?', 'type' => 'boolean', 'format' => 'boolean'])
 *     ->build();
 */
class DetailBuilder
{
    private string $entityClass = '';
    private array $fields = [];

    public function setEntity(string $entityClass): self
    {
        $this->entityClass = $entityClass;
        return $this;
    }

    /**
     * Ajoute un groupe de champs via configuration simple
     */
    public function addFields(array $fieldsConfig): self
    {
        foreach ($fieldsConfig as $field => $options) {
            if (is_string($options)) {
                // Simple format: ['field' => 'Label']
                $this->addField($field, ['label' => $options]);
            } else {
                // Format complet: ['field' => ['label' => '...', 'type' => '...']]
                $this->addField($field, $options);
            }
        }

        return $this;
    }

    /**
     * Ajoute un champ à afficher
     *
     * @param string $field Le chemin du champ (support des relations: 'category.name')
     * @param array $options Options disponibles:
     *   - label: string - Label affiché (défaut: field en titlecase)
     *   - type: string - Type de donnée: 'text', 'email', 'url', 'textarea', 'rich_text', 'boolean', 'date', 'datetime', 'entity', 'collection' (défaut: 'text')
     *   - format: string - Format d'affichage: 'date', 'datetime', 'currency', 'boolean', 'badge', 'badges', 'entity_badge', 'html', 'markdown' (défaut: null)
     *   - callback: callable - Fonction personnalisée recevant l'entité et retournant la valeur
     *   - hidden: bool - Masquer ce champ (défaut: false)
     *   - entity: string - Classe d'entité pour type 'entity'
     *   - entity_label: string - Propriété à afficher pour type 'entity' ou 'collection' (défaut: '__toString')
     *   - null_label: string - Texte affiché si une relation `entity` est nulle
     *   - collection_property: string - Propriété à afficher dans une collection (défaut: '__toString')
     *   - badge_class: string - Classe CSS du badge (défaut: 'bg-secondary')
     *   - null_badge_class: string - Classe CSS du badge quand null (défaut: 'bg-secondary')
     *   - separator: string - Séparateur pour les collections (défaut: ', ')
     *   - empty_text: string - Texte affiché si le champ est vide (défaut: '-')
     *   - class: string - Classes CSS pour la valeur
     *   - enum_map: array - Mapping valeur d'enum => libellé affiché (ex: ['ACTIVE' => 'Actif', 'INACTIVE' => 'Inactif'])
     *   - enum_label_method: string - Nom de la méthode à appeler sur l'enum pour obtenir son libellé (ex: 'label')
     *                                 Si non défini : utilise enum_map, sinon ->value (BackedEnum) ou ->name (UnitEnum)
     */
    public function addField(string $field, array $options = []): self
    {
        $this->fields[] = array_merge([
            'field' => $field,
            'label' => $this->generateLabel($field),
            'type' => 'text',
            'format' => null,
            'callback' => null,
            'hidden' => false,
            'entity' => null,
            'entity_label' => '__toString',
            'null_label' => null,
            'collection_property' => '__toString',
            'badge_class' => 'bg-secondary',
            'null_badge_class' => 'bg-secondary',
            'separator' => ', ',
            'empty_text' => '-',
            'class' => '',
            'enum_map' => [],
            'enum_label_method' => null,
            'enum_color_method' => null,
        ], $options);

        return $this;
    }

    /**
     * Ajoute un champ personnalisé calculé via un callback
     *
     * @param string $field Identifiant du champ
     * @param callable $callback Fonction recevant l'entité et retournant la valeur
     * @param array $options Options d'affichage (label, format, class, empty_text...)
     */
    public function addCustomField(string $field, callable $callback, array $options = []): self
    {
        $options['callback'] = $callback;

        return $this->addField($field, $options);
    }

    private function generateLabel(string $field): string
    {
        $field = str_replace('.', ' ', $field);
        return ucwords(str_replace('_', ' ', $field));
    }

    /**
     * Exclut un champ déjà défini
     */
    public function removeField(string $field): self
    {
        $this->fields = array_filter(
            $this->fields,
            fn($f) => $f['field'] !== $field
        );

        return $this;
    }

    public function build(): array
    {
        return [
            'entityClass' => $this->entityClass,
            'fields' => $this->fields,
        ];
    }
}
