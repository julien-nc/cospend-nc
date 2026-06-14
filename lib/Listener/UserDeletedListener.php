<?php

declare(strict_types=1);

namespace OCA\Cospend\Listener;

use OCA\Cospend\Db\MemberMapper;
use OCA\Cospend\Db\ShareMapper;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\UserDeletedEvent;

/**
 * @template-implements IEventListener<UserDeletedEvent>
 */
class UserDeletedListener implements IEventListener {
	public function __construct(
		private MemberMapper $memberMapper,
		private ShareMapper $shareMapper,
	) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof UserDeletedEvent)) {
			return;
		}

		$userId = $event->getUser()->getUID();
		$this->memberMapper->unsetMemberUserId($userId);
		$this->shareMapper->deleteSharesToUser($userId);
	}
}
