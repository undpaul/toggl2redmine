<?php

namespace derhasi\toggl2redmine\Command;

use AJT\Toggl\TogglClient;
use \Symfony\Component\Console\Command\Command;
use \Symfony\Component\Console\Input\InputArgument;
use \Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use \Symfony\Component\Console\Output\OutputInterface;

/**
 * Symfony command implementation for converting redmine wikipages to git.
 */
class TimeEntrySync extends Command {

  /**
   *  Issue pattern to get the issue number from (in the first match).
   */
  const ISSUE_PATTERN = '/#([0-9]*)/m';

  const ISSUE_SYNCED_FLAG = '#synced';

  /**
   * Number of the match item to get the issue number from.
   */
  const ISSUE_PATTERN_MATCH_ID = 1;

  /**
   * @var \AJT\Toggl\TogglClient;
   */
  protected $togglClient;

  /**
   * @var array
   */
  protected $togglCurrentUser;

  /**
   * @var integer
   */
  protected $togglWorkspaceID;

  /**
   * @var  \Redmine\Client;
   */
  protected $redmineClient;

  /**
   * @var \Symfony\Component\Console\Helper\DialogHelper
   */
  protected $dialog;

  /**
   * @var \Symfony\Component\Console\Output\OutputInterface
   */
  protected $output;

  /**
   * @var \Symfony\Component\Console\Helper\ProgressHelper
   */
  protected $progress;

  /**
   * {@inheritdoc}
   */
  protected function configure()
  {
    $this
      ->setName('time-entry-sync')
      ->setDescription('Converts wiki pages of a redmine project to git')
      ->addArgument(
        'redmineURL',
        InputArgument::REQUIRED,
        'Provide the URL for the redmine installation'
      )
      ->addArgument(
        'redmineAPIKey',
        InputArgument::REQUIRED,
        'The APIKey for accessing the redmine API'
      )
      ->addArgument(
        'tooglAPIKey',
        InputArgument::REQUIRED,
        'API Key for accessing toggl API'
      )
      ->addArgument(
        'togglWorkspaceID',
        InputArgument::REQUIRED,
        'Workspace ID to get time entries from'
      )
      ->addOption(
        'fromDate',
        NULL,
        InputOption::VALUE_REQUIRED,
        'From Date to get Time Entries from',
        '-1 day'
      )
      ->addOption(
        'toDate',
        NULL,
        InputOption::VALUE_REQUIRED,
        'To Date to get Time Entries from',
        'now'
      )
    ;
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    // Prepare helpers.
    $this->dialog = $this->getHelper('dialog');
    $this->output = $output;
    $this->progress = $this->getHelper('progress');

    // Get our necessary arguments from the input.
    $redmineURL = $input->getArgument('redmineURL');
    $redmineAPIKey = $input->getArgument('redmineAPIKey');
    $tooglAPIKey = $input->getArgument('tooglAPIKey');

    // Init toggl.
    $this->togglClient = TogglClient::factory(array('api_key' => $tooglAPIKey));
    $this->togglCurrentUser = $this->togglClient->getCurrentUser();
    $this->togglWorkspaceID = $input->getArgument('togglWorkspaceID');

    // Init redmine.
    $this->redmineClient = new \Redmine\Client($redmineURL, $redmineAPIKey);

    $from = $input->getOption('fromDate');
    $to = $input->getOption('toDate');

    $global_from = new \DateTime($from);
    $global_to = new \DateTime($to);

    // Interval to add 1 second to a time.
    $interval_second = new \DateInterval('PT1S');

    $day_from = clone $global_from;
    // Run each day.
    while ($day_from < $global_to) {

      // Prepare the day to object. We go to the end of the from day, but not
      // any further than the global_to.
      $day_to = clone $day_from;
      $day_to->setTime(23, 59, 59);
      if ($day_to > $global_to) {
        $day_to = clone $global_to;
      }

      $output->writeln(sprintf('Time entries for %s to %s', $day_from->format('D d.m.Y H:i'), $day_to->format('H:i')));

      $entries = $this->getTimeEntries($day_from, $day_to);

      if (empty($entries)) {
        $output->writeln('<comment>No entries given.</comment>');
      }
      else {
        $output->writeln(sprintf('<info>%d entries given.</info>', count($entries)));
        $this->processTimeEntries($entries);
      }

      // The next day to start from.
      $day_from = $day_to->add($interval_second);
    }

    $output->writeln('Finished.');
  }

  /**
   * Process list of time entries.
   *
   * @param $entries
   */
  function processTimeEntries($entries) {

    $process = array();

    // Get the items to process.
    foreach ($entries as $entry) {

      // Get issue number from description.
      if ($issue_id = $this->getIssueNumberFromTimeEntry($entry)) {
        // Check if the entry is already synced.
        if ($this->isTimeEntrySynced($entry)) {
          $this->output->writeln(sprintf("<info>%d:\t %s</info>\t (Issue #%d : SYNCED)", $entry['id'], $entry['description'], $issue_id));
        }
        else {
          $this->output->writeln(sprintf("<comment>%d:\t %s</comment>\t (Issue #%d)", $entry['id'], $entry['description'], $issue_id));
          // Set item to be process.
          $process[] = array(
            'issue' => $issue_id,
            'entry' => $entry
          );
        }
      }
      else {
        $this->output->writeln(sprintf("<error>%d:\t %s\t (No Issue ID found)</error>", $entry['id'], $entry['description']));
      }
    }

    // Simply proceed if no items are to be processed.
    if (empty($process)) {
      $this->output->writeln('<info>All entries synced</info>');
      return;
    }

    // Confirm before we really process.
    if (!$this->dialog->askConfirmation($this->output, sprintf('<question> %d entries not synced. Process now? [y] </question>', count($process)), false)) {
      $this->output->writeln('<error>Sync aborted.</error>');
      return;
    }

    // Process each item.
    $this->progress->start($this->output, count($process));
    foreach ($process as $processData) {
      $this->syncTimeEntry($processData['entry'], $processData['issue']);
      $this->progress->advance();
    }
    $this->progress->finish();
  }

  /**
   * Extracts the redmine issue number from the description.
   *
   * @param $entry
   * @return null
   */
  function getIssueNumberFromTimeEntry($entry) {
    $match = array();
    if (isset($entry['description']) && preg_match(self::ISSUE_PATTERN, $entry['description'], $match)) {
      return $match[self::ISSUE_PATTERN_MATCH_ID];
    }
    return NULL;
  }

  /**
   * Checks if the time entry is synced, by comparing the description.
   *
   * @param $entry
   */
  function isTimeEntrySynced($entry) {
    return strpos($entry['description'], self::ISSUE_SYNCED_FLAG) !== FALSE;
  }

  /**
   * Helper to sync a single time entry to redmine.
   *
   * @param $entry
   * @param $issue_id
   */
  function syncTimeEntry($entry, $issue_id) {
    // Write to redmine.
    $duration = $entry['duration'] / 60 / 60;
    $date = new \DateTime($entry['start']);

    // Fetch unknown errors, or errors that cannot be quickly changed, llike
    // - project was archived
    try {
      $redmine_time_entry = $this->redmineClient->api('time_entry')->create(array(
        'issue_id' => $issue_id,
        'spent_on' => $date->format('Y-m-d'),
        'hours' => $duration,
        // @todo: activity ID mapping
        // 'activity_id' => 9,
        'comments' => $entry['description'],
      ));
    }
    catch (\Exception $e) {
      $this->output->writeln(sprintf("<error>SYNC Failed for %d: %s\t (Issue #%d)\t%s</error>", $entry['id'], $entry['description'], $issue_id, $e->getMessage()));
      return;
    }

    // Check if we got a valid time entry back.
    if (!$redmine_time_entry->id) {
      $this->output->writeln(sprintf("<error>SYNC Failed for %d: %s\t (Issue #%d)\t%s</error>", $entry['id'], $entry['description'], $issue_id, $redmine_time_entry->error));
      return;
    }

    // Update toggl entry with #synced Flag.
    // Will fail with not project ID given:
    // @see https://github.com/arendjantetteroo/guzzle-toggl/pull/4
    $entry['description'] .= ' ' . self::ISSUE_SYNCED_FLAG . "[{$redmine_time_entry->id}]";
    $entry['created_with'] = 'toggl2redmine';
    $ret = $this->togglClient->updateTimeEntry(array(
      'id' => $entry['id'],
      'time_entry' => $entry,
    ));
    if (empty($ret)) {
      $this->output->writeln(sprintf('<error>Updating toggl entry %d failed: %s', $entry['id'], $entry['description']));
    }
  }

  /**
   * Helper to get time entries for given time frame.
   *
   * @param \DateTime $from
   * @param \DateTime $to
   * @return mixed
   */
  function getTimeEntries(\DateTime $from, \DateTime $to) {

    $arguments = array(
      'start_date' => $from->format('c'),
      'end_date' => $to->format('c'),
    );

    $entries = $this->togglClient->GetTimeEntries($arguments);

    foreach ($entries as $id => $entry) {
      // Remove time entries that do not belong to the current account.
      if ($entry['uid'] != $this->togglCurrentUser['id']) {
        unset($entries[$id]);
      }
      // Time entries that are not finished yet, get removed too.
      elseif (empty($entry['stop'])) {
        unset($entries[$id]);
      }
      // Skip entry if it is not part of the workspace.
      elseif ($entry['wid'] != $this->togglWorkspaceID) {
        unset($entries[$id]);
      }
    }

    return $entries;
  }
}