<?php


namespace Calendar\Controller;

use DateTime;
use DateTimeZone;
use Exception;
use Officio\Calendar\Model\Member;
use Recurr\Rule;
use RRule\RfcParser;
use Spatie\IcalendarGenerator\Components\Calendar as IcsCalendar;
use Spatie\IcalendarGenerator\Components\Event as IcsEvent;
use Laminas\Db\Sql\Select;
use Officio\Common\Json;
use Laminas\ModuleManager\ModuleManager;
use Laminas\Session\SessionManager;
use Laminas\View\Model\ViewModel;
use Officio\Api2\Model\AccessToken;
use Officio\BaseController;
use Officio\Calendar\Model\Calendar;
use Officio\Calendar\Model\CalendarAccess;
use Officio\Service\AngularApplicationHost;
use Spatie\IcalendarGenerator\Enums\RecurrenceDay;
use Spatie\IcalendarGenerator\Enums\RecurrenceFrequency;
use Spatie\IcalendarGenerator\ValueObjects\RRule;

/**
 * Calendar Controller
 * TODO Move this whole controller to officio-calendar module
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class IndexController extends BaseController
{

    /** @var AngularApplicationHost */
    protected $_angularApplicationHost;

    /** @var SessionManager */
    protected $_session;

    /** @var ModuleManager */
    protected $_moduleManager;

    public function initAdditionalServices(array $services)
    {
        $this->_angularApplicationHost = $services[AngularApplicationHost::class];
        $this->_session                = $services[SessionManager::class];
        $this->_moduleManager          = $services[ModuleManager::class];
    }

    public function indexAction()
    {
        $view = new ViewModel();
        $view->setTerminal(true);

        if (!$this->_moduleManager->getModule('Officio\\Calendar')) {
            // Officio Calendar module is not loaded
            return $view->setVariables(
                [
                    'calendarEnabled' => false
                ]
            );
        }

        $baseUrl                = rtrim($this->layout()->getVariable('baseUrl'), '/');
        $calendarApplicationUrl = $baseUrl . '/calendar/get-application';

        $html = '<iframe id="angular-application" frameBorder="0" src="' . $calendarApplicationUrl . '" style="flex-grow:1;z-index:110;position:relative;"></iframe>';

        $memberId    = $this->_auth->getCurrentUserId();
        return $view->setVariables(
            [
                'calendarEnabled' => $this->_config['mail']['calendar_enabled'],
                'uid'             => $memberId,
                'protocol'        => $this->_config['urlSettings']['protocol'],
                'proto'           => str_replace('://', '', $this->_config['urlSettings']['protocol']),
                'content'         => $html
            ]
        );
    }

    public function publicAction()
    {
        $view = new ViewModel();
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');
        $view->setVariables(
            [
                'content' => null
            ]
        );

        if (!$this->_moduleManager->getModule('Officio\\Calendar')) {
            // Officio Calendar module is not loaded
            $this->getResponse()->setStatusCode(403);
            $view->setVariable('content', 'Calendar is not enabled.');
            return $view;
        }

        $calendarPublicToken = $this->params()->fromRoute('token');
        if (!$calendarPublicToken) {
            // Officio Calendar module is not loaded
            $this->getResponse()->setStatusCode(404);
            $view->setVariable('content', 'Calendar not found.');
            return $view;
        }

        $calendar = Calendar::loadOne(
            [
                'public_token' => $calendarPublicToken
            ]
        );
        if (!$calendar) {
            // Officio Calendar module is not loaded
            $this->getResponse()->setStatusCode(404);
            $view->setVariable('content', 'Calendar not found.');
            return $view;
        }

        $ical = $this->params()->fromQuery('ical');
        if ($ical !== null) {
            $icsCalendar = IcsCalendar::create();

            $select    = (new Select())
                ->from('cal_events')
                ->where(
                    [
                        'calendar_id' => $calendar->id
                    ]
                );
            $events = $this->_db2->fetchAll($select);

            foreach ($events as $event) {
                $dtStart = new DateTime($event['starts']);
                $dtStart
                    ->setTimezone(new DateTimeZone($event['timezone']));
                $dtEnd = new DateTime($event['ends']);
                $dtEnd
                    ->setTimezone(new DateTimeZone($event['timezone']));
                $icsEvent = IcsEvent::create($event['name'])
                    ->startsAt($dtStart)
                    ->endsAt($dtEnd)
                    ->description($event['text'] ?: '');

                if (!empty($event['all_day'])) {
                    $icsEvent->fullDay();
                }

                $icsCalendar->event(
                    $icsEvent
                );
            }

            $select = (new Select())
                ->from('cal_recurrent_events')
                ->where(
                    [
                        'calendar_id' => $calendar->id
                    ]
                );
            $recurrentEvents = $this->_db2->fetchAll($select);

            foreach ($recurrentEvents as $event) {
                $select = (new Select())
                    ->from('cal_deleted_events')
                    ->where(
                        [
                            'recurrent_event_id' => $event['id']
                        ]
                    );
                $deletedEvents = $this->_db2->fetchAll($select);
                $dateStartStr = null;

                //$parsedRrule = \RRule\RRule::createFromRfcString($event['rrule'])->getRule();
                $lines = explode("\n", $event['rrule'] ?? '');
                foreach ($lines as $line) {
                    $property = RfcParser::parseLine($line, array(
                        'name' => sizeof($lines) > 1 ? null : 'RRULE'  // allow missing property name for single-line RRULE
                    ));

                    if ($property['name'] === 'DTSTART') {
                        if (isset($property['params']['TZID'])) {
                            $tmp = RfcParser::parseTimeZone($property['params']['TZID']);
                            $dateStartStr = new DateTime($property['value'], $tmp);
                        }
                    }
                }
                $parsedRule = new Rule($event['rrule'], $dateStartStr, null, $event['timezone']);

                $freq = RecurrenceFrequency::from($parsedRule->getFreqAsText());

                $rrule = new RRule($freq);
                $rrule
                    ->starting($parsedRule->getStartDate())
                    ->interval($parsedRule->getInterval());

                switch ($freq->value) {
                    case RecurrenceFrequency::weekly()->value:
                        if (count($parsedRule->getByDay()) > 0) {
                            foreach ($parsedRule->getByDay() as $day) {
                                $rrule->onWeekDay(RecurrenceDay::from($day));
                            }
                        }
                        break;
                    case RecurrenceFrequency::monthly()->value:
                    case RecurrenceFrequency::yearly()->value:
                        if (count($parsedRule->getByDay()) > 0 && count($parsedRule->getBySetPosition()) > 0) {
                            $rrule->onWeekDay(RecurrenceDay::from($parsedRule->getByDay()[0]), $parsedRule->getBySetPosition()[0]);
                        }
                        break;
                    default:
                        break;
                }

                if (!empty($parsedRule->getUntil())) {
                    $rrule->until($parsedRule->getUntil());
                }

                if (!empty($parsedRule->getCount())) {
                    $rrule->times($parsedRule->getCount());
                }

                $dtEnd = DateTime::createFromFormat('U', $parsedRule->getStartDate()->getTimestamp() + $event['duration']);
                $dtEnd->setTimezone(new DateTimeZone($event['timezone']));

                $icsEvent = IcsEvent::create($event['name'])
                    ->description($event['text'] ?: '')
                    ->startsAt($parsedRule->getStartDate())
                    ->endsAt($dtEnd)
                    ->rrule($rrule)
                    ->doNotRepeatOn(array_map(function ($deletedEvent) use ($event) {
                        $dtDel = new DateTime($deletedEvent['on']);
                        return $dtDel->setTimezone(new DateTimeZone($event['timezone']));
                    },
                        $deletedEvents));



                if (!empty($event['all_day'])) {
                    $icsEvent->fullDay();
                }

                $icsCalendar->event(
                    $icsEvent
                );
            }

            $content = $icsCalendar->get();

            header("Content-type:text/calendar");
            header('Content-Disposition: attachment; filename="' . $calendar->name . '.ics"');
            header('Content-Length: ' . strlen($content));
            header('Pragma: no-cache');
            header('Expires: 0');
            echo $content;
            header('Connection: close');
        } else {
            $baseUrl                = rtrim($this->layout()->getVariable('baseUrl'), '/');
            $calendarApplicationUrl = "$baseUrl/calendar/get-public-application/$calendarPublicToken";
            $html                   = '<div style="display:flex;width:100%;height:100%;"><iframe id="angular-application" frameBorder="0" src="' . $calendarApplicationUrl . '" style="flex-grow:1;z-index:110;position:relative;"></iframe></div>';
            $view->setVariables(
                [
                    'content' => $html
                ]
            );
        }

        return $view;
    }

    public function getApplicationAction()
    {
        $view = new ViewModel();
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        if (!$this->_moduleManager->getModule('Officio\\Calendar')) {
            // Officio Calendar module is not loaded
            throw new Exception('Calendar module is not installed.');
        }

        $appPath         = 'public/assets/calendar';
        $baseUrl         = rtrim($this->layout()->getVariable('baseUrl'), '/');
        $calendarBaseUrl = $baseUrl . '/assets/calendar';
        $html            = $this->_angularApplicationHost->getEntryHtml($appPath, $calendarBaseUrl);

        $accessTokens = AccessToken::loadBySessionId($this->_session->getId());
        if (!$accessTokens) {
            throw new Exception('Could not find access token for this application.');
        }
        $accessToken = reset($accessTokens);

        $user = Member::load($accessToken->member_id);
        if (!$user) {
            throw new Exception('Could not find a user for the provided access token.');
        }

        $config = $this->_angularApplicationHost->renderConfigurationScript(
            [
                'public_calendar_id' => false,
                'public_base_url'    => $baseUrl . '/calendar/public/',
                'access_token'       => $accessToken ? $accessToken->access_token : false,
                'user'               => Json::encode($user->toArray()),
                'api_url'            => $baseUrl . '/api2/' // TODO Move to the Officio\Api2 module config
            ]
        );

        return $view->setVariables(
            [
                'content' => $config . $html
            ]
        );
    }

    public function getPublicApplicationAction()
    {
        $view = new ViewModel();
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        if (!$this->_moduleManager->getModule('Officio\\Calendar')) {
            // Officio Calendar module is not loaded
            throw new Exception('Calendar module is not installed.');
        }

        $appPath         = 'public/assets/calendar';
        $baseUrl         = rtrim($this->layout()->getVariable('baseUrl'), '/');
        $calendarBaseUrl = $baseUrl . '/assets/calendar';
        $html            = $this->_angularApplicationHost->getEntryHtml($appPath, $calendarBaseUrl);

        $publicToken = $this->params()->fromRoute('token');
        $calendar    = Calendar::loadOne(
            [
                'public_token' => $publicToken
            ]
        );
        if (!$calendar) {
            // Officio Calendar module is not loaded
            throw new Exception('Calendar not found.');
        }

        if ($calendar->public_visibility == CalendarAccess::CALENDAR_ACCESS_NONE) {
            throw new Exception('Access to this calendar is forbidden.');
        }
        $calendarId = $calendar->id;

        $config = $this->_angularApplicationHost->renderConfigurationScript(
            [
                'public_calendar_id' => $calendarId,
                'public_base_url'    => $baseUrl . '/calendar/public/',
                'access_token'       => false,
                'user'               => false,
                'api_url'            => $baseUrl . '/api2/' // TODO Move to the Officio\Api2 module config
            ]
        );

        return $view->setVariables(
            [
                'content' => $config . $html
            ]
        );
    }
}