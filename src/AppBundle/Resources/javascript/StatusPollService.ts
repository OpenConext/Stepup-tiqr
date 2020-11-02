import { PendingRequest, StatusClient } from './Client/StatusClient';

/**
 * Service to request status.
 *
 * Can stop the pending request and timer.
 */
export class StatusPollService {

  private timeoutHandle?: ReturnType<typeof setTimeout>;
  private pendingRequest?: PendingRequest;

  constructor(private statusClient: StatusClient) {

  }

  /**
   * Request status after a fixed time.
   */
  public waitAndRequestStatus(
    successHandler: (status: string) => void,
    errorHandler: (error: unknown) => void) {
    // Make sure there are no timers or request active anymore.
    this.stop();
    this.timeoutHandle = setTimeout(
      () => {
        this.pendingRequest = this.statusClient.request(
          successHandler,
          errorHandler,
        );
      },
      1500,
    );
  }

  /**
   * Stop timer and pending http request.
   */
  public stop() {
    if (this.timeoutHandle) {
      clearTimeout(this.timeoutHandle);
    }
    if (this.pendingRequest) {
      this.pendingRequest.abort();
      this.pendingRequest = undefined;
    }
  }
}
