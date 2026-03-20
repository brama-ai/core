<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\CoderAgent\CoderTaskService;
use App\CoderAgent\DTO\CreateCoderTaskRequest;
use App\CoderAgent\TaskTemplateCatalogInterface;
use App\CoderAgent\TaskTemplateType;
use App\Security\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class CoderCreateController extends AbstractController
{
    public function __construct(
        private readonly TaskTemplateCatalogInterface $templates,
        private readonly CoderTaskService $tasks,
    ) {
    }

    #[Route('/admin/coder/create', name: 'admin_coder_create', methods: ['GET', 'POST'])]
    public function __invoke(Request $request, #[CurrentUser] User $user): Response
    {
        $error = null;
        $values = [
            'title' => '',
            'description' => '',
            'template' => TaskTemplateType::Feature->value,
            'priority' => 5,
            'queue_now' => true,
            'skip_stages' => [],
        ];

        if ($request->isMethod('POST')) {
            $values['title'] = trim((string) $request->request->get('title', ''));
            $values['description'] = (string) $request->request->get('description', '');
            $values['template'] = (string) $request->request->get('template', TaskTemplateType::Feature->value);
            $values['priority'] = max(1, min(10, $request->request->getInt('priority', 5)));
            $values['queue_now'] = '1' === (string) $request->request->get('queue_now', '1');
            $values['skip_stages'] = array_values(array_filter((array) $request->request->all('skip_stages'), 'is_string'));

            try {
                $template = TaskTemplateType::tryFrom($values['template']) ?? TaskTemplateType::Custom;
                $task = $this->tasks->create(new CreateCoderTaskRequest(
                    title: $values['title'],
                    description: $values['description'],
                    templateType: $template,
                    priority: $values['priority'],
                    pipelineConfig: ['skip_stages' => $values['skip_stages']],
                    createdBy: $user->getUserIdentifier(),
                    queueNow: $values['queue_now'],
                ));

                return new RedirectResponse($this->generateUrl('admin_coder_detail', ['id' => $task['id']]));
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }

        return $this->render('admin/coder/create.html.twig', [
            'username' => $user->getUserIdentifier(),
            'templates' => $this->templates->all(),
            'values' => $values,
            'error' => $error,
        ]);
    }
}
