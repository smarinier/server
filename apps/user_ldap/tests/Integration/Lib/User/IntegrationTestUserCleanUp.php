<?php

/**
 * SPDX-FileCopyrightText: 2017-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OCA\User_LDAP\Tests\Integration\Lib\User;

use OCA\User_LDAP\Jobs\CleanUp;
use OCA\User_LDAP\Mapping\UserMapping;
use OCA\User_LDAP\Tests\Integration\AbstractIntegrationTest;
use OCA\User_LDAP\User\DeletedUsersIndex;
use OCA\User_LDAP\User_LDAP;
use OCA\User_LDAP\UserPluginManager;
use OCP\IDBConnection;
use OCP\IUserManager;
use OCP\Server;
use Psr\Log\LoggerInterface;

require_once __DIR__ . '/../../Bootstrap.php';

class IntegrationTestUserCleanUp extends AbstractIntegrationTest {
	/** @var UserMapping */
	protected $mapping;

	/**
	 * prepares the LDAP environment and sets up a test configuration for
	 * the LDAP backend.
	 */
	public function init() {
		require(__DIR__ . '/../../setup-scripts/createExplicitUsers.php');
		parent::init();
		$this->mapping = new UserMapping(Server::get(IDBConnection::class));
		$this->mapping->clear();
		$this->access->setUserMapper($this->mapping);

		$userBackend = new User_LDAP($this->access, Server::get(\OCP\Notification\IManager::class), Server::get(UserPluginManager::class), Server::get(LoggerInterface::class), Server::get(DeletedUsersIndex::class));
		Server::get(IUserManager::class)->registerBackend($userBackend);
	}

	/**
	 * adds a map entry for the user, so we know the username
	 *
	 * @param $dn
	 * @param $username
	 */
	private function prepareUser($dn, $username) {
		// assigns our self-picked oc username to the dn
		$this->mapping->map($dn, $username, 'fakeUUID-' . $username);
	}

	private function deleteUserFromLDAP($dn) {
		$cr = $this->connection->getConnectionResource();
		ldap_delete($cr, $dn);
	}

	/**
	 * tests whether a display name consisting of two parts is created correctly
	 *
	 * @return bool
	 */
	protected function case1() {
		$username = 'alice1337';
		$dn = 'uid=alice,ou=Users,' . $this->base;
		$this->prepareUser($dn, $username);

		$this->deleteUserFromLDAP($dn);

		$job = new CleanUp();
		$job->run([]);

		// user instance must not be requested from global user manager, before
		// it is deleted from the LDAP server. The instance will be returned
		// from cache and may false-positively confirm the correctness.
		$user = Server::get(IUserManager::class)->get($username);
		if ($user === null) {
			return false;
		}
		$user->delete();

		return Server::get(IUserManager::class)->get($username) === null;
	}
}

/** @var string $host */
/** @var int $port */
/** @var string $adn */
/** @var string $apwd */
/** @var string $bdn */
$test = new IntegrationTestUserCleanUp($host, $port, $adn, $apwd, $bdn);
$test->init();
$test->run();
