<?php

declare(strict_types=1);

namespace OCA\Cospend\Middleware;

use Exception;
use OCA\Cospend\Attribute\CospendUserPermissions;
use OCA\Cospend\Exception\CospendUserPermissionsException;
use OCA\Cospend\Service\ProjectService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Middleware;
use OCP\IL10N;
use OCP\IRequest;
use Psr\Log\LoggerInterface;
use ReflectionMethod;

class UserPermissionMiddleware extends Middleware {

	public function __construct(
		private ProjectService $projectService,
		protected IRequest $request,
		private IL10N $l,
		private LoggerInterface $logger,
		private ?string $userId,
	) {
	}

	public function beforeController($controller, $methodName): void {
		$reflectionMethod = new ReflectionMethod($controller, $methodName);

		$attributes = $reflectionMethod->getAttributes(CospendUserPermissions::class);
		error_log('IN UserPermissionMiddleware');

		if (!empty($attributes)) {
			$paramProjectId = $this->request->getParam('projectId');
			$userAccessLevel = $this->projectService->getUserMaxAccessLevel($this->userId, $paramProjectId);
			error_log('ACCESS LEVEL of '.$this->userId.' IS '.$userAccessLevel);

			foreach ($attributes as $attribute) {
				/** @var CospendUserPermissions $cospendAuthAttr */
				$cospendAuthAttr = $attribute->newInstance();
				$minLevel = $cospendAuthAttr->getMinimumLevel();
				if ($userAccessLevel < $minLevel) {
					throw new CospendUserPermissionsException($this->l->t('Insufficient access level'));
				}
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
