import dayjs from '@/utils/dayjs.ts'

type TimeValue = string | number | undefined | null

export default function useTime() {
  const formatTime = (value: TimeValue, format: string, local: boolean = true): string => {
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

  const full_human_date = (time: TimeValue, local: boolean = true): string => {
    return formatTime(time, "dddd, MMMM D, Y", local)
  }

  const short_human_date = (time: TimeValue, local: boolean = true): string => {
    return formatTime(time, "MMM D, Y", local)
  }

  const day = (time: TimeValue, local: boolean = true): string => {
    return formatTime(time, "dddd", local)
  }

  const full_date_time = (time: TimeValue, local: boolean = true): string => {
    return formatTime(time, "dddd, MMMM D, Y h:mm a", local)
  }

  const short_date_time = (time: TimeValue, local: boolean = true): string => {
    return formatTime(time, "MMM D, Y h:mm a", local)
  }

  const time = (time: TimeValue, local: boolean = true): string => {
    return formatTime(time, "h:mma", local)
  }

  const short_date_time_12 = (time: TimeValue, local: boolean = false): string => {
    return formatTime(time, "MM/DD/YYYY h:mm a", local)
  }

  const short_date = (time: TimeValue, local: boolean = true): string => {
    return formatTime(time, "MM/DD/YYYY", local)
  }

  const month_year = (time: TimeValue, local: boolean = true): string => {
    return formatTime(time, "MM/YYYY", local)
  }

  const time_ago = (time: TimeValue, local: boolean = true): string => {
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

  const time_future = (time: TimeValue, local: boolean = true): string => {
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

  const time_calculate = (time: TimeValue, local: boolean = true): string => {
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

  return {
    formatTime,
    full_human_date,
    short_human_date,
    day,
    full_date_time,
    short_date_time,
    time,
    short_date_time_12,
    short_date,
    month_year,
    time_ago,
    time_future,
    time_calculate,
  }
}
