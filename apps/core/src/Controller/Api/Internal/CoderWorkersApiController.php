<?php

declare(strict_types=1);

namespace App\Controller\Api\Internal;

use App\CoderAgent\CoderWorkerRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class CoderWorkersApiController extends AbstractController
{
    public function __construct(
        private readonly CoderWorkerRepositoryInterface $workers,
    ) {
    }

    #[Route('/api/v1/internal/coder/workers', name: 'api_internal_coder_workers', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return $this->json(['workers' => $this->workers->findAll()]);
    }
}
