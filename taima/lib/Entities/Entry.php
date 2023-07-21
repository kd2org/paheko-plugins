<?php

namespace Paheko\Plugin\Taima\Entities;

use Paheko\Entity;

use DateTime;

class Entry extends Entity
{
	const TABLE = 'plugin_taima_entries';

	protected $id;
	protected $user_id;
	protected $task_id;
	protected $date;
	protected $notes;
	protected $duration;
	protected $timer_started;
	protected $year;
	protected $week;

	protected $_types = [
		'id'            => 'int',
		'user_id'       => '?int',
		'task_id'       => '?int',
		'date'          => 'date',
		'notes'         => '?string',
		'duration'      => '?int',
		'timer_started' => '?int',
		'year'          => 'int',
		'week'          => 'int',
	];

	public function selfCheck(): void
	{
		parent::selfCheck();

		if (!$this->task_id) {
			$this->task_id = null;
		}

		$this->assert(!(is_null($this->duration) && is_null($this->timer_started)), 'Duration cannot be NULL if timer is not running');
	}

	public function setDate(DateTime $date)
	{
		$this->set('year', (int) $date->format('o'));
		$this->set('week', (int) $date->format('W'));
		$this->set('date', $date);
	}

	public function setDateString(string $date)
	{
		$this->setDate($this->filterUserValue('date', $date, 'date'));
	}

	public function setDuration(string $duration = null)
	{
		$duration = trim($duration);

		if ($duration === '') {
			$this->set('duration', null);
			$this->start();
			return;
		}

		if (preg_match('/^(\d+)[h:](\d+)$/', $duration, $match)) {
			$minutes = $match[1] * 60 + $match[2];
		}
		elseif (preg_match('/^(\d+)(?:[.,](\d+))?$/', $duration, $match)) {
			$minutes = $match[1] * 60;

			if (!empty($match[2])) {
				$minutes += 60 * ($match[2] / 100);
			}
		}
		else {
			throw new \InvalidArgumentException('Invalid duration: ' . $duration);
		}

		$this->set('timer_started', null);
		$this->set('duration', (int) $minutes);
	}

	public function start(): void
	{
		if ($this->timer_started) {
			return;
		}

		$this->set('timer_started', time());
	}

	public function stop(): void {
		if (!$this->timer_started) {
			return;
		}

		$this->set('duration', intval($this->duration + ceil((time() - $this->timer_started) / 60)));
		$this->set('timer_started', null);
	}
}
