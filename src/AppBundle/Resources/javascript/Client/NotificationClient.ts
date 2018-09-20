export class NotificationClient {
  constructor(private apiUrl: string) {

  }

  /**
   * Request status form the API.
   */
  public send(callback: (result: string) => void, errorCallback: (error: unknown) => void) {
    jQuery.post(this.apiUrl, callback).fail(errorCallback);
  }
}
