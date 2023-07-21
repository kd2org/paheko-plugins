<?php

namespace Paheko\Plugin\Git_Documents;

use Paheko\Users\Session;

use Paheko\Plugin;
use Paheko\Users\Emails;

use KD2\Mail_Message;

class GitDocuments
{
	const MESSAGE = "Modification depuis l'interface de Garradin";

	static public function sync()
	{
		$session = Session::getInstance();

		if ($session->isLogged()) {
			$user = $session->getUser();
			$user = sprintf('%s <%s>', $user->identite, $user->email);
			$user_arg = sprintf('--author=%s', escapeshellarg($user));
		}
		else {
			$user_arg = '';
		}

		$cmd = '{ cd %s && git reset --merge 2>&1 && git add -A 2>&1 && git commit -a -m %s %s 2>&1; } > /dev/null &';
		$cmd = sprintf($cmd, escapeshellarg(\Paheko\FILE_STORAGE_CONFIG), escapeshellarg(self::MESSAGE), $user_arg);

		putenv('LANG=fr_FR.UTF-8');
		exec($cmd, $out, $code);

		if ($code) {
			throw new \RuntimeException(sprintf('Git command (%s) failed: %s', $cmd, implode("\n", $out)));
		}
	}

	static public function sendDiff(): void
	{
		$plugin = new Plugin('git_documents');
		$config = $plugin->getConfig();

		if (empty($config->diff_email)) {
			return;
		}

		$body = '';
		$last = trim(shell_exec(sprintf('cd %s && git rev-parse HEAD', escapeshellarg(\Paheko\FILE_STORAGE_CONFIG))));

		if (!empty($config->last_commit_hash)) {
			if ($last == $config->last_commit_hash) {
				return;
			}

			$revs = sprintf('HEAD...%s', $config->last_commit_hash);
		}
		else {
			$revs = 'HEAD...HEAD^';
		}

		$plugin->setConfig('last_commit_hash', $last);

		putenv('LANG=fr_FR.UTF-8');
		$diff_cmd = sprintf('cd %s && git log -p %s',
			escapeshellarg(\Paheko\FILE_STORAGE_CONFIG),
			escapeshellarg($revs)
		);

		$body = shell_exec($diff_cmd . ' --no-color');

		if (!trim($body)) {
			return;
		}

		$html = null;

		if (shell_exec('which aha')) {
			$html = shell_exec($diff_cmd . ' --color --word-diff=color | aha');
		}

		$msg = new Mail_Message;
		$msg->setHeader('To', $config->diff_email);
		$msg->setHeader('From', Emails::getFromHeader());
		$msg->setHeader('Subject', 'Modification de documents');
		$msg->setBody($body);

		if ($html) {
			$msg->addPart('text/html', $html);
		}

		Emails::sendMessage(Emails::CONTEXT_SYSTEM, $msg);
	}
}