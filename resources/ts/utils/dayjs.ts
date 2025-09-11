import dayjs from "dayjs"
import utc from "dayjs/plugin/utc"
import timezone from "dayjs/plugin/timezone"
import relativeTime from "dayjs/plugin/relativeTime"
import isSameOrAfter from "dayjs/plugin/isSameOrAfter"
import isSameOrBefore from "dayjs/plugin/isSameOrBefore"
import isBetween from "dayjs/plugin/isBetween"
import isToday from "dayjs/plugin/isToday"
import isTomorrow from "dayjs/plugin/isTomorrow"
import isYesterday from "dayjs/plugin/isYesterday"
import minMax from "dayjs/plugin/minMax"

dayjs.extend(utc)
dayjs.extend(timezone)
dayjs.extend(relativeTime)
dayjs.extend(isSameOrAfter)
dayjs.extend(isSameOrBefore)
dayjs.extend(isBetween)
dayjs.extend(isToday)
dayjs.extend(isTomorrow)
dayjs.extend(isYesterday)
dayjs.extend(minMax)

type TimeValue = string | number | undefined | null

function formatTime(value: TimeValue, format: string, local: boolean = true): string {
  if (!value) {
    return ""
  }
  let date = dayjs.utc(value)
  if (!date.isValid()) {
    return ""
  }
  if (local) {
    date = date.local()
  }
  return date.format(format)
}

function full_human_date(time: TimeValue, local: boolean = true): string {
  return formatTime(time, "dddd, MMMM D, Y", local)
}

function short_human_date(time: TimeValue, local: boolean = true): string {
  return formatTime(time, "MMM D, Y", local)
}

function day(time: TimeValue, local: boolean = true): string {
  return formatTime(time, "dddd", local)
}

function full_date_time(time: TimeValue, local: boolean = true): string {
  return formatTime(time, "dddd, MMMM D, Y h:mm a", local)
}

function short_date_time(time: TimeValue, local: boolean = true): string {
  return formatTime(time, "MMM D, Y h:mm a", local)
}

function time(time: TimeValue, local: boolean = true): string {
  return formatTime(time, "h:mma", local)
}

function short_date_time_12(time: TimeValue, local: boolean = false): string {
  return formatTime(time, "MM/DD/YYYY h:mm a", local)
}

function short_date_time_24(time: TimeValue, local: boolean = false): string {
  return formatTime(time, "MM/DD/YYYY HH:mm", local)
}

function short_date(time: TimeValue, local: boolean = true): string {
  return formatTime(time, "MM/DD/YYYY", local)
}

function short_hour(time: TimeValue, local: boolean = true): string {
  return formatTime(time, "HH:mm", local)
}

function month_year(time: TimeValue, local: boolean = true): string {
  return formatTime(time, "MM/YYYY", local)
}

function time_ago(time: TimeValue, local: boolean = true): string {
  if (!time) {
    return ""
  }
  let date = dayjs.utc(time)
  if (!date.isValid()) {
    return ""
  }
  if (local) {
    date = date.local()
  }
  return dayjs.min(date.endOf('day'), dayjs()).fromNow()
}

function time_future(time: TimeValue, local: boolean = true): string {
  if (!time) {
    return ""
  }
  let date = dayjs.utc(time)
  if (!date.isValid()) {
    return ""
  }
  if (local) {
    date = date.local()
  }
  const start = date.clone().startOf('day')
  const end = date.clone().add(1, 'second').startOf('day')

  if (start.isSame(end)) {
    return "Today"
  }
  return dayjs.max(start).to(end)
}

function time_calculate(time: TimeValue, local: boolean = true): string {
  if (!time) {
    return ""
  }
  let date = dayjs.utc(time)
  if (!date.isValid()) {
    return ""
  }
  if (local) {
    date = date.local()
  }
  if (date.isAfter()) {
    return time_future(time, local)
  } else {
    return time_ago(time, local)
  }
}

export default dayjs

export {
  formatTime,
  full_human_date,
  short_human_date,
  day,
  full_date_time,
  short_date_time,
  time,
  short_date_time_12,
  short_date_time_24,
  short_date,
  short_hour,
  month_year,
  time_ago,
  time_future,
  time_calculate,
  dayjs
}
