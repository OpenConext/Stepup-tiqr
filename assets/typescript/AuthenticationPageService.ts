import { StatusPollService } from './StatusPollService';
import { ComponentCollection } from './Component/ComponentCollection';
import { NotificationClient } from './Client/NotificationClient';
import { Component } from './Component/Component';

export class AuthenticationPageService {
  private allComponents: Component;

  constructor(private statusPollingService: StatusPollService,
              private notificationClient: NotificationClient,
              private spinnerComponent: Component,
              private qrComponent: Component,
              private otpFormComponent: Component,
              private challengeExpiredComponent: Component,
              private statusErrorComponent: Component,
              private notificationErrorComponent: Component) {
    this.allComponents = new ComponentCollection([
      this.spinnerComponent,
      this.qrComponent,
      this.otpFormComponent,
      this.challengeExpiredComponent,
      this.statusErrorComponent,
      this.notificationErrorComponent,
    ]);
  }

  /**
   * The user can scan the QR code, keep the polling alive.
   */
  public switchToManual(): void {
    this.allComponents.hide();
    this.qrComponent.show();
  }

  /**
   * Wait for the OTP te be filled.
   *
   * We don't stop polling, maybe they clicked wrong, and are still scanning the QR code.
   */
  public switchToOtp() {
    this.allComponents.hide();
    this.qrComponent.show();
    this.otpFormComponent.show();
  }

  /**
   * When status request to the API challengeExpired.
   *
   * Stop polling and ask user to retry.
   */
  public switchToChallengeHasExpired() {
    this.statusPollingService.stop();
    this.allComponents.hide();
    this.challengeExpiredComponent.show();
  }

  /**
   * Failed to send notification. Show message that it failed and give the user to the option to scan the QR code.
   */
  public switchToNotificationFailed() {
    this.allComponents.hide();
    this.qrComponent.show();
    this.notificationErrorComponent.show();
  }

  /**
   * There is no device registered to send a notification, show QR code instead.
   */
  public switchToNoDevice() {
    this.allComponents.hide();
    this.qrComponent.show();
  }

  /**
   * Do a request to send the push notification and start the polling service for the actual status.
   */
  public switchToPolling() {
    this.allComponents.hide();
    this.spinnerComponent.show();
    this.notificationClient.send(this.notificationReceivedHandler, this.notificationErrorHandler);
    this.statusPollingService.waitAndRequestStatus(this.statusReceivedHandler, this.statusErrorHandler);
  }

  /**
   * We got an status error.
   */
  public switchToStatusRequestError() {
    this.statusPollingService.stop();
    this.allComponents.hide();
    this.statusErrorComponent.show();
  }

  /* istanbul ignore next */
  public reloadPage() {
    window.location.reload();
  }

  /**
   * This will handle the status given by the AuthenticationController.authenticationStatusAction.
   */
  private statusReceivedHandler = (status: string) => {
    switch (status) {
      case 'pending':
        this.statusPollingService.waitAndRequestStatus(this.statusReceivedHandler, this.statusErrorHandler);
        break;
      case 'challenge-expired':
        this.switchToChallengeHasExpired();
        break;
      case 'needs-refresh':
        this.reloadPage();
        break;
      default:
        this.switchToStatusRequestError();
        break;
    }
  };

  /**
   * Handler when actual request to the API failed.
   */
  private statusErrorHandler = () => {
    this.switchToStatusRequestError();
  };

  /**
   * Status response handler for the notification.
   */
  private notificationReceivedHandler = (status: string) => {
    switch (status) {
      case 'success':
        // Do nothing, push notification is send successfully so we can keep on polling.
        break;
      case 'error':
        this.switchToNotificationFailed();
        break;
      case 'no-device':
        this.switchToNoDevice();
        break;
    }
  };

  /**
   * When the push notification failed. (Maybe the name resolvers timed out etc)
   */
  private notificationErrorHandler = () => {
    this.switchToNotificationFailed();
  };

}
