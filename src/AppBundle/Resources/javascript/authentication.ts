import { StatusPollService } from './StatusPollService';
import { StatusClient } from './Client/StatusClient';
import { AuthenticationPageService } from './AuthenticationPageService';
import { NotificationClient } from './Client/NotificationClient';
import { SlideAbleComponent } from './Component/SlideAbleComponent';
import { HideAbleComponent } from './Component/HideAbleComponent';

declare global {
  interface Window {
    bootstrapAuthentication: (statusApiUrl: string, notificationApiUrl: string) => AuthenticationPageService;
  }
}

window.bootstrapAuthentication = (statusApiUrl: string, notificationApiUrl: string) => {
  const statusClient = new StatusClient(statusApiUrl);
  const notificationClient = new NotificationClient(notificationApiUrl);
  const pollingService = new StatusPollService(statusClient);

  const spinnerComponent = new SlideAbleComponent(jQuery('.spinner-container'));
  const qrComponent = new SlideAbleComponent(jQuery('#qr'));
  const otpFormComponent = new HideAbleComponent(jQuery('#otpform'));
  const challengeExpiredComponent = new HideAbleComponent(jQuery('#challengeExpired'));
  const statusErrorComponent = new HideAbleComponent(jQuery('#status-request-error'));
  const notificationErrorComponent = new HideAbleComponent(jQuery('#notificationError'));

  const authenticationPageService = new AuthenticationPageService(
    pollingService,
    notificationClient,
    spinnerComponent,
    qrComponent,
    otpFormComponent,
    challengeExpiredComponent,
    statusErrorComponent,
    notificationErrorComponent,
  );
  authenticationPageService.switchToPolling();
  return authenticationPageService;
};
