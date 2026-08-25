import {type App} from "vue"
import {downloadFile, fileUrl, formatFileSize} from "@modules/Files/resources/ts/file"

/**
 * Module plugin — picked up by the modules plugin glob in
 * the kernel's plugins/index.ts and handed the app instance after the core
 * plugins are installed. This is how a module registers globals, Vue plugins,
 * or mixins; $file used to live in the kernel and moved here with the vertical.
 */
export default function (app: App): void {
  app.config.globalProperties.$file = {
    url: fileUrl,
    download: downloadFile,
    formatFileSize: formatFileSize,
  }
}
