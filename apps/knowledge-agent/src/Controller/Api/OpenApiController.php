<?php

declare(strict_types=1);

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Yaml\Yaml;

final class OpenApiController extends AbstractController
{
    public function __construct(
        private readonly string $projectDir,
    ) {
    }

    #[Route('/api/v1/knowledge/openapi.json', name: 'api_knowledge_openapi', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $yamlPath = $this->projectDir . '/openapi/knowledge-api.yaml';
        
        if (!file_exists($yamlPath)) {
            return $this->json(['error' => 'OpenAPI specification not found'], Response::HTTP_NOT_FOUND);
        }
        
        try {
            $yamlContent = file_get_contents($yamlPath);
            if (false === $yamlContent) {
                throw new \RuntimeException('Failed to read OpenAPI specification file');
            }
            
            $spec = Yaml::parse($yamlContent);
            
            $response = new JsonResponse($spec);
            $response->setPublic();
            $response->setMaxAge(3600); // Cache for 1 hour
            
            return $response;
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to parse OpenAPI specification'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}