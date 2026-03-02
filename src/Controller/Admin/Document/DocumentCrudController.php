<?php

declare(strict_types=1);

namespace App\Controller\Admin\Document;

use App\Entity\Document;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use Symfony\Component\Validator\Constraints\File;

class DocumentCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Document::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Document')
            ->setEntityLabelInPlural('Documents')
            ->setSearchFields(['titre', 'typeDocument', 'typeDetecte', 'insuranceReference'])
            ->setDefaultSort(['dateUpload' => 'DESC'])
            ->setPaginatorPageSize(20)
            ->setEntityPermission('ROLE_ADMIN');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('titre', 'Titre');
        yield TextField::new('typeDocument', 'Type');
        yield ImageField::new('cheminFichier', 'Fichier PDF')
            ->setBasePath('/uploads/documents')
            ->setUploadDir('public/uploads/documents')
            ->setUploadedFileNamePattern('[slug]-[contenthash].[extension]')
            ->setFileConstraints([
                new File([
                    'maxSize' => '5M',
                    'mimeTypes' => ['application/pdf'],
                    'mimeTypesMessage' => 'Seuls les fichiers PDF sont autorisés (max 5 Mo)',
                ]),
            ])
            ->hideOnIndex();
        yield DateTimeField::new('dateUpload', 'Date upload')->setFormat('dd/MM/yyyy HH:mm');
        yield TextareaField::new('resumeAi', 'Résumé IA')->hideOnIndex();
        yield ArrayField::new('motsCles', 'Mots-clés')->hideOnIndex();
        yield TextField::new('typeDetecte', 'Type détecté (IA)');
        yield TextField::new('insuranceReference', 'Réf. assurance');
        yield AssociationField::new('categorie', 'Catégorie');
        yield AssociationField::new('user', 'Utilisateur');
    }
}
