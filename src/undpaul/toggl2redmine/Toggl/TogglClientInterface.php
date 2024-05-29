<?php

namespace undpaul\toggl2redmine\Toggl;

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

}
