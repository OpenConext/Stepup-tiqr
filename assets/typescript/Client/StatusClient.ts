import jQuery from 'jquery';

export interface PendingRequest {
  abort: () => void;
}

export class StatusClient {
  constructor(private apiUrl: string) {

  }

  /**
   * Request status form the API.
   */
  public request(callback: (status: string) => void, errorCallback: (error: unknown) => void): PendingRequest {
    return jQuery.get(this.apiUrl, callback).fail(errorCallback);
  }
}
