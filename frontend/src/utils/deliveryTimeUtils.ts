/**
 * Утилиты для работы с временными слотами доставки
 * Реализует правило lead time: минимальное время доставки - 3 часа от текущего момента
 */

/**
 * Проверяет, доступен ли временной слот для выбранной даты
 * @param slotTime - время слота в формате "HH:MM" (например, "14:00")
 * @param selectedDate - выбранная дата в формате "YYYY-MM-DD" (например, "2025-01-15")
 * @param leadTimeHours - минимальное время в часах (по умолчанию 3)
 * @returns true, если слот доступен для выбора
 */
export function isTimeSlotAvailable(
  slotTime: string,
  selectedDate: string,
  leadTimeHours: number = 3
): boolean {
  if (!slotTime || !selectedDate) {
    return false;
  }

  const now = new Date();
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
  const selected = new Date(selectedDate + "T00:00:00");
  const selectedDay = new Date(selected.getFullYear(), selected.getMonth(), selected.getDate());

  // Если выбрана дата в будущем (не сегодня), все слоты доступны
  if (selectedDay > today) {
    return true;
  }

  // Если выбрана сегодняшняя дата, проверяем lead time
  const [hours, minutes] = slotTime.split(":").map(Number);
  const slotDateTime = new Date(selectedDate + `T${hours.toString().padStart(2, "0")}:${minutes.toString().padStart(2, "0")}:00`);

  // Минимальное доступное время = текущее время + lead time
  const minAvailableTime = new Date(now.getTime() + leadTimeHours * 60 * 60 * 1000);

  return slotDateTime >= minAvailableTime;
}

/**
 * Фильтрует массив временных слотов, оставляя только доступные
 * @param timeSlots - массив временных слотов в формате "HH:MM"
 * @param selectedDate - выбранная дата в формате "YYYY-MM-DD"
 * @param leadTimeHours - минимальное время в часах (по умолчанию 3)
 * @returns отфильтрованный массив доступных слотов
 */
export function getAvailableTimeSlots(
  timeSlots: string[],
  selectedDate: string | null,
  leadTimeHours: number = 3
): string[] {
  if (!selectedDate) {
    return [];
  }

  return timeSlots.filter((slot) => isTimeSlotAvailable(slot, selectedDate, leadTimeHours));
}

/**
 * Проверяет, является ли слот отключенным (недоступным)
 * Используется для disabled состояния в UI
 * @param slotTime - время слота в формате "HH:MM"
 * @param selectedDate - выбранная дата в формате "YYYY-MM-DD"
 * @param leadTimeHours - минимальное время в часах (по умолчанию 3)
 * @returns true, если слот должен быть отключен
 */
export function isTimeSlotDisabled(
  slotTime: string,
  selectedDate: string | null,
  leadTimeHours: number = 3
): boolean {
  if (!selectedDate) {
    return true;
  }

  return !isTimeSlotAvailable(slotTime, selectedDate, leadTimeHours);
}

/**
 * Фильтрует временные слоты, удаляя опцию "Как можно скорее"
 * @param timeSlots - массив временных слотов
 * @returns отфильтрованный массив без опции "Как можно скорее"
 */
export function filterOutAsapOption(timeSlots: string[]): string[] {
  const asapVariants = [
    "asap",
    "как можно скорее",
    "как можно быстрее",
    "сейчас",
    "немедленно",
    "as soon as possible",
  ];

  return timeSlots.filter(
    (slot) => !asapVariants.some((asap) => slot.toLowerCase().includes(asap.toLowerCase()))
  );
}

