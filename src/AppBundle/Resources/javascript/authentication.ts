import { StatusPollService } from './StatusPollService';
import { StatusClient } from './Client/StatusClient';
import { AuthenticationPageService } from './AuthenticationPageService';
import { NotificationClient } from './Client/NotificationClient';
import { SlideableComponent } from './Component/SlideableComponent';
import { HideableComponent } from './Component/HideableComponent';

declare global {
  interface Window {
    bootstrapAuthentication: (statusApiUrl: string, notificationApiUrl: string) => AuthenticationPageService;
  }
}

window.bootstrapAuthentication = (statusApiUrl: string, notificationApiUrl: string) => {
  const statusClient = new StatusClient(statusApiUrl);
  const notificationClient = new NotificationClient(notificationApiUrl);
  const pollingService = new StatusPollService(statusClient);

  const spinnerComponent = new SlideableComponent(jQuery('.spinner-container'));
  const qrComponent = new SlideableComponent(jQuery('#qr'));
  const otpFormComponent = new HideableComponent(jQuery('#otpform'));
  const challengeExpiredComponent = new HideableComponent(jQuery('#challengeExpired'));
  const statusErrorComponent = new HideableComponent(jQuery('#status-request-error'));
  const notificationErrorComponent = new HideableComponent(jQuery('#notificationError'));

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
