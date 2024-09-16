<?php

namespace undpaul\toggl2redmine\Toggl;

use Carbon\Carbon;

/**
 * Interface for custom toggle client.
 */
interface TogglClientInterface {

  /**
   * Return details for the current user.
   *
   * @param bool $with_related_data
   *   (Optional) Retrieve user related data (clients, projects, tasks, tags,
   *   workspaces, time entries, etc.).
   *
   * @return \stdClass
   *   Details of the current user as stdClass object.
   */
  public function getMe(bool $with_related_data = FALSE): \stdClass;

  /**
   * Get time entries started in a specific time range
   *
   * @param \Carbon\Carbon|null $startDate
   *   Start of date range.
   * @param \Carbon\Carbon|null $endDate
   *   End of date range.
   */
  public function timeEntries(?Carbon $startDate = NULL, ?Carbon $endDate = NULL);

}
