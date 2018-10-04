import { StatusPollService } from './StatusPollService';
import { RegistrationStatusComponent } from './Component/RegistrationStatusComponent';
import { Component } from './Component/Component';

export class RegistrationStateMachine {
  public static readonly IDLE = '1';
  public static readonly INITIALIZED = '2';
  public static readonly RETRIEVED = '3';
  public static readonly PROCESSED = '4';
  public static readonly FINALIZED = '5';
  /**
   * Client-side only status.
   */
  public static readonly ERROR = 'ERROR';
  private previousStatus = RegistrationStateMachine.IDLE;

  constructor(private statusPollingService: StatusPollService,
              private statusUi: RegistrationStatusComponent,
              private qrCode: Component,
              private finalizedUrl: string) {
  }

  public start() {
    this.scheduleNextPoll();
    this.statusUi.showOpenTiqrApp();
  }

  /**
   * This will handle the status given by the RegistrationController.registrationStatusAction.
   */
  private statusReceivedHandler = (status: string) => {
    switch (status) {
      case RegistrationStateMachine.IDLE:
        if (this.previousStatus !== RegistrationStateMachine.IDLE) {
          this.qrCode.hide();
          this.statusPollingService.stop();
          this.statusUi.showExpiredSessionStatus();
          this.previousStatus = RegistrationStateMachine.ERROR;
          return;
        }
        this.statusUi.showOpenTiqrApp();
        this.scheduleNextPoll();
        break;
      case RegistrationStateMachine.INITIALIZED:
        this.statusUi.showOpenTiqrApp();
        this.qrCode.show();
        this.scheduleNextPoll();
        break;
      case RegistrationStateMachine.RETRIEVED:
        this.statusUi.showAccountActivationHelp();
        this.scheduleNextPoll();
        this.qrCode.hide();
        break;
      case RegistrationStateMachine.PROCESSED:
        this.statusUi.showOneMomentPlease();
        this.scheduleNextPoll();
        this.qrCode.hide();
        break;
      case RegistrationStateMachine.FINALIZED:
        this.statusPollingService.stop();
        this.statusUi.showFinalized();
        this.qrCode.hide();
        document.location.replace(this.finalizedUrl);
        break;
      default:
        this.unknownError();
        return;
    }
    this.previousStatus = status;
  };

  private scheduleNextPoll() {
    this.statusPollingService.waitAndRequestStatus(this.statusReceivedHandler, this.unknownError);
  }

  private unknownError = () => {
    this.qrCode.hide();
    this.statusUi.showUnknownErrorHappened();
    this.statusPollingService.stop();
    this.previousStatus = RegistrationStateMachine.ERROR;
  };

}
