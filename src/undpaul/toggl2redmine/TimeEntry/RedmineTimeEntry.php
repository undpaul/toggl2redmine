<?php

namespace undpaul\toggl2redmine\TimeEntry;

use undpaul\toggl2redmine\RedmineTimeEntryActivity;

class RedmineTimeEntry extends TimeEntryBase {

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->raw['comments'];
  }

  /**
   * {@inheritdoc}
   */
  public function getIssueID() {
    return (int) $this->raw['issue']['id'];
  }

  /**
   * {@inheritdoc}
   */
  public function getHours() {
    return number_format($this->raw['hours'], 2);
  }

  /**
   * {@inheritdoc}
   */
  public function getDateString() {
    return $this->raw['spent_on'];
  }

  /**
   * Get time entry activity.
   *
   * @return \undpaul\toggl2redmine\RedmineTimeEntryActivity
   */
  public function getActivity() {
    return new RedmineTimeEntryActivity($this->raw['activity']);
  }

}
