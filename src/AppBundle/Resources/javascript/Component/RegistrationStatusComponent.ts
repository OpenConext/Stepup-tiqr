import 'jquery';

export class RegistrationStatusComponent {

  /**
   * Your session has expired.
   * Refresh the page and try again.
   * Or click 'Cancel' to return to Home
   */
  public showExpiredSessionStatus() {
    this.show('ul.status.expired');
  }

  /**
   * Open the tiqr app on your phone.
   * Scan the QR code with the tiqr app.
   */
  public showOpenTiqrApp() {
    this.show('ul.status.initialized');
  }

  /**
   * The 'Account activation' page will appear on your phone.
   * Click 'OK' in the tiqr app to activate your account.
   * Create a PIN for your tiqr account<br/>Please memorise your PIN, this PIN cannot be changed later.
   * After entering your PIN in the tiqr app you will proceed to the next step automatically.
   */
  public showAccountActivationHelp() {
    this.show('ul.status.retrieved');
  }

  /**
   * One moment please...
   */
  public showOneMomentPlease() {
    this.show('div.status.processed');
  }

  /**
   * Finalized.
   */
  public showFinalized() {
    this.show('div.status.finalized');
  }

  /**
   * Unknown error happened. Please try again by refreshing your browser.
   */
  public showUnknownErrorHappened() {
    this.show('div.status.error');
  }

  private hideAll() {
    jQuery('.status-container >').hide();
  }

  private show(selector: string) {
    this.hideAll();
    jQuery(selector).show();
  }

}
