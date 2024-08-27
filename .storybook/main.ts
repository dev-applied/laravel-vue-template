import type { StorybookConfig } from "@storybook/vue3-vite"
import { mergeConfig } from "vite"
import * as path from "node:path"

const config: StorybookConfig = {
  stories: [
    "../stories/**/*.mdx",
    "../stories/**/*.stories.@(js|jsx|mjs|ts|tsx)"
  ],
  addons: [
    "@storybook/addon-links",
    "@storybook/addon-essentials",
    "@chromatic-com/storybook",
    "@storybook/addon-interactions",
    '@storybook/addon-viewport'
  ],
  framework: {
    name: "@storybook/vue3-vite",
    options: {
      docgen: "vue-component-meta",
    }
  },
  async viteFinal(config) {
    config = mergeConfig(config, {
      resolve: {
        alias: {
          ...config.resolve?.alias,
          "@": path.resolve(__dirname, "../resources/ts"),
          "@scss": path.resolve(__dirname, "../resources/scss"),
        }
      },
      server: {
        host: true,
        origin: '',
        fs: {
          strict: false
        },
        strictPort: true,
        hmr: {
          protocol: 'wss',
          clientPort: 443,
          overlay: true,
        }
      },
    })

    return config
  }
}
export default config
