<?php
namespace puremvc\php\core;
use puremvc\php\interfaces\IView;
use puremvc\php\interfaces\IMediator;
use puremvc\php\interfaces\INotification;
use puremvc\php\interfaces\IObserver;
use puremvc\php\patterns\observer\Observer;


/**
 * PureMVC Port to PHP originally translated by Asbjørn Sloth Tønnesen
 *
 * @author Omar Gonzalez :: omar@almerblank.com
 * @author Hasan Otuome :: hasan@almerblank.com
 *
 * Created on Sep 24, 2008
 * PureMVC - Copyright(c) 2006-2008 Futurescale, Inc., Some rights reserved.
 * Your reuse is governed by the Creative Commons Attribution 3.0 Unported License
 */

/**
 * A Singleton <code>IView</code> implementation.
 *
 * <P>
 * In PureMVC, the <code>View</code> class assumes these responsibilities:
 * <UL>
 * <LI>Maintain a cache of <code>IMediator</code> instances.</LI>
 * <LI>Provide methods for registering, retrieving, and removing <code>IMediators</code>.</LI>
 * <LI>Managing the observer lists for each <code>INotification</code> in the application.</LI>
 * <LI>Providing a method for attaching <code>IObservers</code> to an <code>INotification</code>'s observer list.</LI>
 * <LI>Providing a method for broadcasting an <code>INotification</code>.</LI>
 * <LI>Notifying the <code>IObservers</code> of a given <code>INotification</code> when it broadcast.</LI>
 * </UL>
 *
 * @see org.puremvc.php.patterns.mediator.Mediator Mediator
 * @see org.puremvc.php.patterns.observer.Observer Observer
 * @see org.puremvc.php.patterns.observer.Notification Notification
 */
class View implements IView
{
    // Mapping of Mediator names to Mediator instances
    protected $mediatorMap;

    // Mapping of Notification names to Observer lists
    protected $observerMap;

    // Singleton instance
    protected static $instance;

    /**
     * Constructor.
     *
     * <P>
     * This <code>IView</code> implementation is a Singleton,
     * so you should not call the constructor
     * directly, but instead call the static Singleton
     * Factory method <code>View.getInstance()</code>
     *
     * @throws Error Error if Singleton instance has already been constructed
     */
    private function __construct()
    {
        $this->mediatorMap = [];
        $this->observerMap = [];
        $this->initializeView();
    }

    /**
     * Initialize the Singleton View instance.
     *
     * <P>
     * Called automatically by the constructor, this
     * is your opportunity to initialize the Singleton
     * instance in your subclass without overriding the
     * constructor.</P>
     */
    protected function initializeView()
    {
    }

    /**
     * View Singleton Factory method.
     *
     * @return View Singleton instance of <code>View</code>
     */
    public static function getInstance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Register an <code>IObserver</code> to be notified
     * of <code>INotifications</code> with a given name.
     *
     * @param mixed                             $notificationName
     * @param IObserver $observer
     */
    public function registerObserver($notificationName, IObserver $observer)
    {
        if (isset($this->observerMap[$notificationName]) && null !== $this->observerMap[$notificationName]) {
            array_push($this->observerMap[$notificationName], $observer);
        } else {
            $this->observerMap[$notificationName] = [$observer];
        }
    }

    /**
     * Notify the <code>IObservers</code> for a particular <code>INotification</code>.
     *
     * <P>
     * All previously attached <code>IObservers</code> for this <code>INotification</code>'s
     * list are notified and are passed a reference to the <code>INotification</code> in
     * the order in which they were registered.</P>
     *
     * @param INotification $notification
     */
    public function notifyObservers(INotification $notification)
    {
        if (isset($this->observerMap[$notification->getName()])) {
            // Copy observers from reference array to working array,
            // since the reference array may change during the notification loop
            $observers = $this->observerMap[$notification->getName()];

            foreach ($observers as $observer) {
                $observer->notifyObserver($notification);
            }
        }
    }

    /**
     * Remove Observer
     *
     * Remove a group of observers from the observer list for a given Notification name.
     *
     * @param string $notificationName Which observer list to remove from.
     * @param mixed $notifyContext Remove the observers with this object as their notifyContext
     */
    public function removeObserver($notificationName, $notifyContext)
    {
        //Is there registered Observers for the notification under inspection
        if (!isset($this->observerMap[$notificationName])) {
            return;
        }

        // the observer list for the notification under inspection
        $observers = $this->observerMap[$notificationName];

        // find the observer for the notifyContext
        for ($i = 0, $iMax = count($observers); $i < $iMax; $i++) {
            if ($observers[$i]->compareNotifyContext($notifyContext)) {
                // there can only be one Observer for a given notifyContext
                // in any given Observer list, so remove it and break
                array_splice($observers, $i, 1);
                break;
            }
        }

        // Also, when a Notification's Observer list length falls to
        // zero, delete the notification key from the observer map
        if (0 === count($observers)) {
            unset($this->observerMap[$notificationName]);
        }
    }

    /**
     * Register an <code>IMediator</code> instance with the <code>View</code>.
     *
     * <P>
     * Registers the <code>IMediator</code> so that it can be retrieved by name,
     * and further interrogates the <code>IMediator</code> for its
     * <code>INotification</code> interests.</P>
     * <P>
     * If the <code>IMediator</code> returns any <code>INotification</code>
     * names to be notified about, an <code>Observer</code> is created encapsulating
     * the <code>IMediator</code> instance's <code>handleNotification</code> method
     * and registering it as an <code>Observer</code> for all <code>INotifications</code> the
     * <code>IMediator</code> is interested in.</p>
     *
     * @param IMediator $mediator
     */
    public function registerMediator(IMediator $mediator)
    {
        // do not allow re-registration (you must to removeMediator fist)
        if ($this->hasMediator($mediator->getMediatorName())) {
            return;
        }

        // Register the Mediator for retrieval by name
        $this->mediatorMap[$mediator->getMediatorName()] = $mediator;

        // Get Notification interests, if any.
        $interests = $mediator->listNotificationInterests();

        if (0 < count($interests)) {
            // Create Observer
            $observer = new Observer('handleNotification', $mediator);

            // Register Mediator as Observer for its list of Notification interests
            foreach ($interests as $interest) {
                $this->registerObserver($interest, $observer);
            }
        }

        // Alert the Mediator that it has been registered
        $mediator->onRegister();
    }

    /**
     * Retrieve an <code>IMediator</code> from the <code>View</code>.
     *
     * @param mixed $mediatorName
     * @return the <code>IMediator</code> instance previously registered with the given <code>mediatorName</code>.
     */
    public function retrieveMediator($mediatorName)
    {
        if ($this->hasMediator($mediatorName)) {
            return $this->mediatorMap[$mediatorName];
        }
    }

    /**
     * Check to see if a Mediator is registered with the View.
     *
     * @param mixed $mediatorName
     * @return bool
     */
    public function hasMediator($mediatorName)
    {
        return isset($this->mediatorMap[$mediatorName]);
    }

    /**
     * Remove an <code>IMediator</code> from the <code>View</code>.
     *
     * @param mixed $mediatorName
     * @return mixed|null
     */
    public function removeMediator($mediatorName)
    {
        if ($this->hasMediator($mediatorName)) {
            // Retrieve the named mediator
            $mediator = $this->mediatorMap[$mediatorName];

            // for every notification this mediator is interested in...
            $interests = $mediator->listNotificationInterests();
            foreach ($interests as $interest) {
                $this->removeObserver($interest, $mediator);
            }

            // Remove the reference from the map
            unset($this->mediatorMap[$mediatorName]);

            // alert the mediator that it has been removed
            $mediator->onRemove();

            return $mediator;
        }

        return null;
    }
}
