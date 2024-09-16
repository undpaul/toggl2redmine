<?php

namespace undpaul\toggl2redmine\Toggl;

use Carbon\Carbon;
use Ixudra\Toggl\TogglService;

class TogglClient extends TogglService implements \undpaul\toggl2redmine\Toggl\TogglClientInterface {

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

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function timeEntries(?Carbon $startDate = NULL, ?Carbon $endDate = NULL) {
    $request_data = array();
    if (!empty($startDate) || !empty($endDate)) {
      $request_data = [
        'start_date' => $startDate->toDateTimeLocalString() . 'Z',
        'end_date' => $endDate->toDateTimeLocalString() . 'Z',
      ];
    }

    return $this->sendGetMessage($this->baseUrl . $this->apiVersionUrl . '/me/time_entries', $request_data);
  }

}
