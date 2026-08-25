import type {App} from "vue"
import AppAnnouncementsHost from "@modules/Announcements/resources/ts/components/AppAnnouncementsHost.vue"

/**
 * Registers the host globally so a project drops
 * &lt;AppAnnouncementsHost /&gt; into its layout without an import.
 */
export default function (app: App): void {
  app.component('AppAnnouncementsHost', AppAnnouncementsHost)
}
