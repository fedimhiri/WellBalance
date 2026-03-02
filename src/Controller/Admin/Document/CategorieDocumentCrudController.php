<?php

declare(strict_types=1);

namespace App\Controller\Admin\Document;

use App\Entity\CategorieDocument;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;

class CategorieDocumentCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return CategorieDocument::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Catégorie')
            ->setEntityLabelInPlural('Catégories')
            ->setSearchFields(['nom', 'description'])
            ->setDefaultSort(['nom' => 'ASC'])
            ->setEntityPermission('ROLE_ADMIN');
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('nom', 'Nom');
        yield TextareaField::new('description', 'Description')->hideOnIndex();
    }
}
