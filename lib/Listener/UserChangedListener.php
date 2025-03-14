<?php

declare(strict_types=1);

namespace OCA\Cospend\Listener;

use OCA\Cospend\Db\MemberMapper;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\UserChangedEvent;

/**
 * @template-implements IEventListener<UserChangedEvent>
 */
class UserChangedListener implements IEventListener {
	public function __construct(
		private MemberMapper $memberMapper,
	) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof UserChangedEvent)) {
			return;
		}

		if ($event->getFeature() === 'displayName') {
			$this->memberMapper->updateMemberNameByUserId($event->getUser()->getUID(), $event->getValue());
		}
	}
}
