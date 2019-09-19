import jQuery from 'jquery';

export class NotificationClient {
  constructor(private apiUrl: string) {

  }

  /**
   * Request status form the API.
   */
  public send(callback: (result: string) => void, errorCallback: (error: unknown) => void) {
    jQuery.ajaxSetup(
      {
        // Wait max 10 seconds.
        timeout: 1000 * 10,
      },
    );
    jQuery.post(this.apiUrl, callback).fail(errorCallback);
  }
}
