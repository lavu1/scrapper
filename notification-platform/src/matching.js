function values(value) {
  if (value === undefined || value === null || value === '') return [];
  return (Array.isArray(value) ? value : [value])
    .map((item) => String(item).trim().toLowerCase())
    .filter(Boolean);
}

function includesAny(haystack, needles) {
  if (!needles.length) return true;
  const text = values(haystack);
  return needles.some((needle) => text.some((candidate) => candidate === needle || candidate.includes(needle)));
}

export function matchesFilters(content, filters = {}) {
  const keywords = values(filters.keywords ?? filters.keyword);
  if (keywords.length) {
    const searchable = [
      content.title,
      content.description,
      content.company,
      content.location,
      content.category,
      content.jobType,
      content.country,
      content.studyLevel,
      content.field,
      content.funding,
    ].filter(Boolean).join(' ').toLowerCase();
    if (!keywords.some((keyword) => searchable.includes(keyword))) return false;
  }

  const checks = [
    ['category', content.category],
    ['categoryId', content.categoryId],
    ['location', content.location],
    ['country', content.country],
    ['jobType', content.jobType],
    ['type', content.type],
    ['studyLevel', content.studyLevel],
    ['field', content.field],
    ['funding', content.funding],
    ['browser', content.browser],
    ['device', content.device],
    ['platform', content.platform],
  ];

  return checks.every(([key, actual]) => {
    const expected = values(filters[key]);
    return !expected.length || includesAny(actual, expected);
  });
}

export function dueAtForFrequency(frequency, now = new Date()) {
  const date = new Date(now);
  if (frequency === 'weekly') {
    date.setUTCDate(date.getUTCDate() + ((8 - date.getUTCDay()) % 7 || 7));
    date.setUTCHours(7, 0, 0, 0);
    return date;
  }
  if (frequency === 'daily') {
    date.setUTCDate(date.getUTCDate() + 1);
    date.setUTCHours(7, 0, 0, 0);
    return date;
  }
  return date;
}
