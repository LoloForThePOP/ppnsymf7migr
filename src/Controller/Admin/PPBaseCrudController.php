<?php

namespace App\Controller\Admin;

use App\Entity\PPBase;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class PPBaseCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return PPBase::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Projet')
            ->setEntityLabelInPlural('Projets')
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield TextField::new('title')->setColumns(6);
        yield TextField::new('goal')->setColumns(6);
        yield TextareaField::new('textDescription')->onlyOnForms()->setColumns(12);
        yield TextField::new('originLanguage')->onlyOnForms();
        yield TextField::new('ingestion.sourceUrl', 'Source URL')->onlyOnForms();
        yield BooleanField::new('isPublished');
        yield DateTimeField::new('createdAt')->onlyOnIndex();
        yield DateTimeField::new('updatedAt')->onlyOnIndex();
    }
}
