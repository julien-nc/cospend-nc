<?php

declare(strict_types=1);

namespace OCA\Cospend\Middleware;

use Exception;
use OCA\Cospend\Attribute\CospendPublicAuth;
use OCA\Cospend\Db\Share;
use OCA\Cospend\Db\ShareMapper;
use OCA\Cospend\Exception\CospendPublicAuthNotValidException;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Middleware;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use Psr\Log\LoggerInterface;
use ReflectionMethod;

class PublicAuthMiddleware extends Middleware {

	public function __construct(
		private ShareMapper $shareMapper,
		protected IRequest $request,
		private IL10N $l,
		private LoggerInterface $logger,
		private IConfig $config,
	) {
	}

	public function beforeController($controller, $methodName): void {
		$reflectionMethod = new ReflectionMethod($controller, $methodName);

		$attributes = $reflectionMethod->getAttributes(CospendPublicAuth::class);

		if (!empty($attributes)) {
			$paramToken = $this->request->getParam('token');
			$paramPassword = $this->request->getParam('password');
			try {
				$share = $this->shareMapper->getLinkOrFederatedShareByToken($paramToken);
			} catch (DoesNotExistException $e) {
				throw new CospendPublicAuthNotValidException(
					$this->l->t('Project not found'), Http::STATUS_UNAUTHORIZED,
					$paramToken, $paramPassword, 'invalid token'
				);
			}
			if ($share->getType() === Share::TYPE_PUBLIC_LINK && $share->getPassword() !== null && $paramPassword !== $share->getPassword()) {
				throw new CospendPublicAuthNotValidException(
					$this->l->t('Project password is invalid'), Http::STATUS_UNAUTHORIZED,
					$paramToken, $paramPassword, 'invalid link password'
				);
			}

			foreach ($attributes as $attribute) {
				/** @var CospendPublicAuth $cospendAuthAttr */
				$cospendAuthAttr = $attribute->newInstance();
				$minLevel = $cospendAuthAttr->getMinimumLevel();
				if ($share->getAccesslevel() < $minLevel) {
					throw new CospendPublicAuthNotValidException(
						$this->l->t('Insufficient access level'), Http::STATUS_UNAUTHORIZED,
						$paramToken, $paramPassword, 'insufficient permissions'
					);
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
			if (!$this->config->getSystemValueBool('debug', false)) {
				$response->throttle([
					'reason' => $exception->reason,
					'token' => $exception->token,
					'password' => $exception->password,
				]);
			}

			$this->logger->debug($exception->getMessage(), [
				'exception' => $exception,
			]);
			return $response;
		}

		throw $exception;
	}
}
