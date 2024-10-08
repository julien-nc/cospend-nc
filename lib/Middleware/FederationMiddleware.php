<?php

declare(strict_types=1);

namespace OCA\Cospend\Middleware;

use Exception;
use OCA\Cospend\Attribute\SupportFederatedProject;
use OCA\Cospend\Controller\ApiController;
use OCA\Cospend\Db\Invitation;
use OCA\Cospend\Db\InvitationMapper;
use OCA\Cospend\Exception\CospendUserPermissionsException;
use OCA\Cospend\Service\FederatedProjectService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Middleware;
use OCP\IRequest;
use Psr\Log\LoggerInterface;
use ReflectionMethod;

class FederationMiddleware extends Middleware {

	public function __construct(
		protected IRequest $request,
		private LoggerInterface $logger,
		private InvitationMapper $invitationMapper,
	) {
	}

	public function beforeController($controller, $methodName): void {
		if (!$controller instanceof ApiController) {
			return;
		}
		$reflectionMethod = new ReflectionMethod($controller, $methodName);

		$attributes = $reflectionMethod->getAttributes(SupportFederatedProject::class);

		if (!empty($attributes)) {
			$paramProjectId = $this->request->getParam('projectId');
			// federated projects only
			if (str_contains($paramProjectId, '@')) {
				[$remoteProjectId, $remoteServerUrl] = FederatedProjectService::parseFederatedProjectId($paramProjectId);
				$invitations = $this->invitationMapper->getInvitationsForUser($controller->userId, Invitation::STATE_ACCEPTED, $remoteServerUrl, $remoteProjectId);
				if (empty($invitations)) {
					throw new Exception('No such federated project is owned by the current user', Http::STATUS_UNAUTHORIZED);
				}
				$controller->projectService = \OC::$server->get(FederatedProjectService::class);
				$controller->projectService->userId = $controller->userId;
			}
		}
	}

	/**
	 * @param Controller $controller the controller that is being called
	 * @param string $methodName the name of the method that will be called on
	 *                           the controller
	 * @param Exception $exception the thrown exception
	 * @return Response a Response object or null in case that the exception could not be handled
	 * @throws Exception the passed in exception if it can't handle it
	 */
	public function afterException($controller, $methodName, Exception $exception): Response {
		if ($exception instanceof CospendUserPermissionsException) {
			$response = new JSONResponse(
				['message' => $exception->getMessage()],
				$exception->getCode()
			);

			$this->logger->debug($exception->getMessage(), [
				'exception' => $exception,
			]);
			return $response;
		}

		throw $exception;
	}
}
