<?php

declare(strict_types=1);

namespace OCA\Cospend\Middleware;

use Exception;
use OCA\Cospend\Attribute\CospendPublicAuth;
use OCA\Cospend\Exception\CospendPublicAuthNotValidException;

use OCA\Cospend\Service\ProjectService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Middleware;
use OCP\IL10N;
use OCP\IRequest;
use Psr\Log\LoggerInterface;
use ReflectionMethod;

class PublicAuthMiddleware extends Middleware {

	public function __construct(
		private ProjectService $projectService,
		protected IRequest $request,
		private IL10N $l,
		private LoggerInterface $logger,
	) {
	}

	public function beforeController($controller, $methodName): void {
		$reflectionMethod = new ReflectionMethod($controller, $methodName);

		$attributes = $reflectionMethod->getAttributes(CospendPublicAuth::class);

		if (!empty($attributes)) {
			$paramToken = $this->request->getParam('token');
			$paramPassword = $this->request->getParam('password');
			$publicShareInfo = $this->projectService->getProjectInfoFromShareToken($paramToken);
			if ($publicShareInfo === null) {
				throw new CospendPublicAuthNotValidException($this->l->t('Project not found'), Http::STATUS_UNAUTHORIZED);
			}
			if (!is_null($publicShareInfo['password']) && $paramPassword !== $publicShareInfo['password']) {
				throw new CospendPublicAuthNotValidException($this->l->t('Project password is invalid'), Http::STATUS_UNAUTHORIZED);
			}

			foreach ($attributes as $attribute) {
				/** @var CospendPublicAuth $cospendAuthAttr */
				$cospendAuthAttr = $attribute->newInstance();
				$minLevel = $cospendAuthAttr->getMinimumLevel();
				if ($publicShareInfo['accesslevel'] < $minLevel) {
					throw new CospendPublicAuthNotValidException($this->l->t('Insufficient access level'), Http::STATUS_UNAUTHORIZED);
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
		if ($exception instanceof CospendPublicAuthNotValidException) {
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
