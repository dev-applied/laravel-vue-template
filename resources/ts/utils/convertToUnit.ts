export default function(str: string | number, unit = "px") {
  if (str == null || str === "") {
    return undefined
  } else if (isNaN(+str)) {
    return String(str)
  } else {
    return `${Number(str)}${unit}`
  }
}
