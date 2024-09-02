<?php

declare(strict_types=1);

namespace OCA\Cospend\Middleware;

use Exception;
use OCA\Cospend\Attribute\CospendFederation;
use OCA\Cospend\Controller\ApiController;
use OCA\Cospend\Db\InvitationMapper;
use OCA\Cospend\Exception\CospendUserPermissionsException;
use OCA\Cospend\Service\FederatedProjectService;
use OCP\AppFramework\Controller;
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

		$attributes = $reflectionMethod->getAttributes(CospendFederation::class);

		if (!empty($attributes)) {
			$paramProjectId = $this->request->getParam('projectId');
			// federated projects only
			if (str_contains($paramProjectId, '@')) {
				[$remoteServerUrl, $remoteProjectId] = explode('@', $paramProjectId);
				$invitation = $this->invitationMapper->getByRemoteServerAndId($remoteServerUrl, $remoteProjectId);
				if ($invitation->getUserId() !== $controller->userId) {
					throw new Exception('This federated project is not owned by the current user');
				}
				$controller->projectService = \OC::$server->get(FederatedProjectService::class);
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
