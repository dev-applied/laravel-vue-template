import * as url from 'url'
import path from 'path'

import { createChecker } from 'vue-component-meta'

const __dirname = url.fileURLToPath(new URL('.', import.meta.url))

const checkerOptions = {
  forceUseTs: true,
  schema: { ignore: ['MyIgnoredNestedProps'] },
  printer: { newLine: 1 },
}

const checker = createChecker(
  path.join(__dirname, '../../../tsconfig.json'),
  checkerOptions,
)
const componentPath = path.join(__dirname, '../components/AppTable.vue')
const _meta = checker.getComponentMeta(componentPath)
// console.log(JSON.stringify(meta.props, null,  1))
