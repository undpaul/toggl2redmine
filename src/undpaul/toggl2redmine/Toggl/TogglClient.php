<?php

namespace undpaul\toggl2redmine\Toggl;

use Ixudra\Toggl\TogglService;

class TogglClient extends TogglService implements TogglClientInterface {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function getMe(bool $with_related_data = FALSE): \stdClass {
    $request_data = [];
    if ($with_related_data === TRUE) {
      $request_data['with_related_data'] = TRUE;
    }

    $data = $this->sendGetMessage($this->baseUrl . $this->apiVersionUrl . '/me', $request_data);

    return $data;
  }

}
