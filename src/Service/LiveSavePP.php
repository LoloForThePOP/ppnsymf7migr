<?php

namespace App\Service;

use App\Entity\Need;
use App\Entity\PPBase;
use App\Entity\Slide;
use App\Entity\Embeddables\PPBase\OtherComponentsModels\WebsiteComponent;
use App\Entity\Embeddables\PPBase\OtherComponentsModels\QuestionAnswerComponent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\Validator\ValidatorInterface;


class LiveSavePP {

    /**
     * Limit the list of entities that can be targeted to avoid arbitrary class resolution.
     */
    private const ENTITY_CLASS_MAP = [
        'ppbase' => PPBase::class,
        'slide' => Slide::class,
        'need' => Need::class,
    ];

    private const DIRECT_PROPERTIES = [
        'goal',
        'title',
        'textDescription',
        'caption',
        'description',
    ];

    private const COMPONENT_PROPERTIES = [
        'websites',
        'questionsAnswers',
    ];

    private const COMPONENT_STORAGE_KEYS = [
        'websites' => 'websites',
        'questionsAnswers' => 'questions_answers',
    ];

    /**
     * Map OtherComponents (websites, questionsAnswers, …) sub-properties
     * to the PHP class/property that already declares constraints.
     *
     * @var array<string, array<string, array{string, string, array|null}>>
     */
    private const COMPONENT_VALIDATION_MAP = [
        'websites' => [
            'title' => [WebsiteComponent::class, 'title', ['input']],
            'url' => [WebsiteComponent::class, 'url', ['input']],
        ],
        'questionsAnswers' => [
            'question' => [QuestionAnswerComponent::class, 'question', ['input']],
            'answer' => [QuestionAnswerComponent::class, 'answer', ['input']],
        ],
    ];


    protected $entityName;
    protected $entityId;
    protected $property;
    protected $subProperty;
    protected $subId;
    protected $content;

    protected $entity;
    protected $pp;
    protected $entityClass;

    protected $em;
    protected $validator;
    protected $security;




    public function __construct(Security $security, EntityManagerInterface $em, ValidatorInterface $validator)
    {
      
        $this->security = $security;
        $this->em = $em;
        $this->validator = $validator;

    }


    /**
     * Normalize incoming metadata so the rest of the service can trust its state.
     */
    public function hydrate(
        string $entityName,
        int $entityId,
        string $property,
        ?string $subId,
        ?string $subProperty,
        string $content
    ): void {
        $normalizedEntity = strtolower($entityName);
        if (!isset(self::ENTITY_CLASS_MAP[$normalizedEntity])) {
            throw new \InvalidArgumentException(sprintf('Type d’entité "%s" non supporté.', $entityName));
        }

        $this->entityName = $normalizedEntity;
        $this->entityClass = self::ENTITY_CLASS_MAP[$normalizedEntity];
        $this->entityId = $entityId;
        $this->property = trim($property);
        $this->subProperty = $subProperty !== null ? trim($subProperty) : null;
        $this->subId = $subId !== null ? trim($subId) : null;
        $this->content = $content;

        $this->setEntityToUpdate();
        $this->setPPToUpdate();

    }


  
    


    /**
     * Resolve the concrete Doctrine entity or bail with a consistent error.
     */
    public function setEntityToUpdate(): void
    {
        $entity = $this->em->getRepository($this->entityClass)->find($this->entityId);

        if ($entity === null) {
            throw new \RuntimeException('Élément introuvable.');
        }

        $this->entity = $entity;
    }

    /**
     * Allow to get the project presentation concerned by the update
     * This further allows to check if user can edit this presentation
     */
    public function setPPToUpdate(){

        if (!method_exists($this->entity, 'getProjectPresentation')) {
            throw new \LogicException(sprintf(
                'Entity "%s" must expose getProjectPresentation() to support live save.',
                $this->entity::class
            ));
        }

        return $this->pp = $this->entity->getProjectPresentation();

    }

    /**
     * Check if user is granted to edit the concerned project presentation
     */
    public function allowUserAccess(){

        return $this->security->isGranted('edit', $this->pp);

    }

    
    /**
     * Check if the entity, property, or subproperty can be ajax updated.
     * The goal is to fail fast before hitting Doctrine setters or array mutations.
     */
    public function allowItemAccess(): bool
    {
        if (!isset(self::ENTITY_CLASS_MAP[$this->entityName])) {
            throw new \InvalidArgumentException('Type d’entité non supporté.');
        }

        if (!in_array($this->property, $this->allowedProperties(), true)) {
            throw new \InvalidArgumentException('Propriété non autorisée.');
        }

        if ($this->requiresSubProperty() && $this->subProperty === null) {
            throw new \InvalidArgumentException('Sous-propriété manquante.');
        }

        if ($this->isComponentProperty() && $this->subId === null) {
            throw new \InvalidArgumentException('Identifiant d’élément manquant.');
        }

        if ($this->isDirectPropertyMutation()) {
            $setter = 'set' . ucfirst($this->property);
            if (!method_exists($this->entity, $setter)) {
                throw new \LogicException(sprintf(
                    'Impossible de modifier "%s" sur %s.',
                    $this->property,
                    $this->entity::class
                ));
            }
        }

        return true;
    }

    private function isComponentProperty(): bool
    {
        return in_array($this->property, self::COMPONENT_PROPERTIES, true);
    }

    private function isDirectPropertyMutation(): bool
    {
        return in_array($this->property, self::DIRECT_PROPERTIES, true);
    }

    private function requiresSubProperty(): bool
    {
        return $this->isComponentProperty();
    }

    private function resolveComponentStorageKey(): string
    {
        return self::COMPONENT_STORAGE_KEYS[$this->property] ?? $this->property;
    }

    /**
     * Aggregate all properties that can be edited through the inline workflow.
     */
    private function allowedProperties(): array
    {
        return array_merge(self::DIRECT_PROPERTIES, self::COMPONENT_PROPERTIES);
    }

    
    /**
    * Check if content to update is valid.
    * Ex : property : websites; subproperty : url; content : www.propon.org
    */
    public function validateContent(){

        // Special handling for OtherComponents (websites, questionsAnswers…)
        if (
            $this->subProperty !== null
            && isset(self::COMPONENT_VALIDATION_MAP[$this->property][$this->subProperty])
        ) {
            [$class, $field, $groups] = self::COMPONENT_VALIDATION_MAP[$this->property][$this->subProperty];

            $errors = $this->validator->validatePropertyValue(
                $class,
                $field,
                $this->content,
                $groups
            );

            return $errors->count() > 0 ? $errors[0]->getMessage() : true;
        }

        // Fall back to the entity metadata if the property exists on the entity
        if (is_object($this->entity) && property_exists($this->entity, $this->property)) {
            $errors = $this->validator->validatePropertyValue(
                $this->entity::class,
                $this->property,
                $this->content
            );

            return $errors->count() > 0 ? $errors[0]->getMessage() : true;
        }

        return true;

    }

    public function getPresentation(): PPBase
    {
        return $this->pp;
    }

    public function save(){

        switch ($this->property) {

            case 'websites': //these special cases : we update the following property in PPBase entity : $otherComponents
            case 'questionsAnswers':

                $storageKey = $this->resolveComponentStorageKey();
                $item = $this->pp->getOCItem($storageKey, (string) $this->subId); //ex: a website

                if ($item === null) {
                    throw new \RuntimeException('Élément introuvable.');
                }

                if (!array_key_exists($this->subProperty, $item)) {
                    throw new \InvalidArgumentException(sprintf(
                        'Champ "%s" introuvable sur le composant.',
                        $this->subProperty
                    ));
                }

                $item[$this->subProperty] = $this->content; // updating item subproperty (ex: website url)
                $this->pp->setOCItem($storageKey, (string) $this->subId, $item);

                break;

            default: //updating an entity property (ex: a Slide $caption)

                $propertySetterName =  'set'.ucfirst($this->property); 
                // Setters on the aggregate are trusted at this point thanks to allowItemAccess().
                $this->entity->$propertySetterName($this->content);

                break;

        }

        $this->em->flush();

    }
    












}
