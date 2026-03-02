<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Document;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Document>
 */
class DocumentRepository extends ServiceEntityRepository
{
    private const ALLOWED_SORT_FIELDS = [
        'dateUpload',
        'titre',
        'typeDocument',
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Document::class);
    }

    /**
     * @return Document[]
     */
    public function searchAndSort(
        ?string $search,
        string $sortField = 'dateUpload',
        string $direction = 'DESC',
        ?User $user = null
    ): array {
        if (!\in_array($sortField, self::ALLOWED_SORT_FIELDS, true)) {
            $sortField = 'dateUpload';
        }

        $direction = strtoupper($direction);
        if (!\in_array($direction, ['ASC', 'DESC'], true)) {
            $direction = 'DESC';
        }

        $qb = $this->createQueryBuilder('d');

        if (null !== $user) {
            $qb->andWhere('d.user = :user')
               ->setParameter('user', $user);
        }

        if (null !== $search && '' !== trim($search)) {
            $qb->andWhere('d.titre LIKE :search OR d.typeDocument LIKE :search')
               ->setParameter('search', '%' . trim($search) . '%');
        }

        $qb->orderBy('d.' . $sortField, $direction);

        return $qb->getQuery()->getResult();
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countAnalyzed(): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.resumeAi IS NOT NULL')
            ->andWhere("d.resumeAi != ''")
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countInsuranceSubmitted(): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.insuranceReference IS NOT NULL')
            ->andWhere("d.insuranceReference != ''")
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countAnomalies(): int
    {
        $keywords = ['anomalie', 'anormal', 'urgence', 'critique', 'pathologique', 'élevé', 'anormalement'];
        $qb = $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.resumeAi IS NOT NULL');

        $orX = $qb->expr()->orX();
        foreach ($keywords as $i => $kw) {
            $orX->add('LOWER(d.resumeAi) LIKE :kw' . $i);
        }
        $qb->andWhere($orX);
        foreach ($keywords as $i => $kw) {
            $qb->setParameter('kw' . $i, '%' . $kw . '%');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function countAllDocuments(): int
    {
        return $this->countAll();
    }

    /**
     * @return array<int, array{nom: string, count: int}>
     */
    public function countByCategory(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = 'SELECT c.id, c.nom, COUNT(d.id) as cnt
                FROM document d
                LEFT JOIN categorie_document c ON d.categorie_id = c.id
                GROUP BY d.categorie_id, c.id, c.nom
                ORDER BY cnt DESC';
        $result = $conn->executeQuery($sql)->fetchAllAssociative();

        return array_map(static fn (array $row): array => [
            'nom' => null !== $row['nom'] ? (string) $row['nom'] : 'Sans catégorie',
            'count' => (int) $row['cnt'],
        ], $result);
    }

    /**
     * @return array<int, array{month: string, count: int}>
     */
    public function countByMonth(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = "SELECT DATE_FORMAT(d.date_upload, '%Y-%m') as month, COUNT(d.id) as cnt
                FROM document d
                GROUP BY month
                ORDER BY month DESC
                LIMIT 12";
        $result = $conn->executeQuery($sql)->fetchAllAssociative();

        return array_map(static fn (array $row): array => [
            'month' => $row['month'],
            'count' => (int) $row['cnt'],
        ], $result);
    }

    /**
     * @return array<int, array{user_id: int, user_name: string, count: int}>
     */
    public function countByUser(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = 'SELECT u.id as user_id, COALESCE(u.username, u.email) as user_name, COUNT(d.id) as cnt
                FROM document d
                JOIN user u ON d.user_id = u.id
                GROUP BY d.user_id, u.id, u.username, u.email
                ORDER BY cnt DESC';
        $result = $conn->executeQuery($sql)->fetchAllAssociative();

        return array_map(static fn (array $row): array => [
            'user_id' => (int) $row['user_id'],
            'user_name' => $row['user_name'] ?? 'Inconnu',
            'count' => (int) $row['cnt'],
        ], $result);
    }

    /**
     * @return array<int, array{insurance_reference: string, count: int}>
     */
    public function countByInsurance(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = "SELECT COALESCE(d.insurance_reference, '—') as insurance_reference, COUNT(d.id) as cnt
                FROM document d
                WHERE d.insurance_reference IS NOT NULL AND d.insurance_reference != ''
                GROUP BY d.insurance_reference
                ORDER BY cnt DESC
                LIMIT 10";
        $result = $conn->executeQuery($sql)->fetchAllAssociative();

        return array_map(static fn (array $row): array => [
            'insurance_reference' => $row['insurance_reference'],
            'count' => (int) $row['cnt'],
        ], $result);
    }

    /**
     * @return Document[]
     */
    public function findPaginated(int $page = 1, int $limit = 15, ?User $user = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->orderBy('d.dateUpload', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        if (null !== $user) {
            $qb->andWhere('d.user = :user')->setParameter('user', $user);
        }

        return $qb->getQuery()->getResult();
    }

    public function countPaginated(?User $user = null): int
    {
        $qb = $this->createQueryBuilder('d')->select('COUNT(d.id)');
        if (null !== $user) {
            $qb->andWhere('d.user = :user')->setParameter('user', $user);
        }
        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
