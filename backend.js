async function api(action, data) {
  const options = {
    method: data ? 'POST' : 'GET',
    credentials: 'same-origin',
    headers: {}
  };

  if (data) {
    options.headers['Content-Type'] = 'application/json';
    options.body = JSON.stringify(data);
  }

  const response = await fetch('api.php?action=' + encodeURIComponent(action), options);
  const result = await response.json();

  if (!response.ok || !result.ok) {
    throw new Error(result.error || 'خطا در ارتباط با سرور');
  }
  return result;
}

function safeHTML(value) {
  return String(value).replace(/[&<>"']/g, ch => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;'
  }[ch]));
}

function showApiError(error) {
  if (typeof toast === 'function') toast(error.message || 'خطا در ارتباط با سرور');
  else console.error(error);
}

async function backendLoadWall() {
  try {
    const result = await api('wall');
    const notes = result.notes.length ? result.notes : wallDefaults;

    $('wallGrid').innerHTML = notes.map(n => `
      <div class="wall-note reveal">
        ${safeHTML(n.text)}
        <small>— ${safeHTML(n.who)}</small>
      </div>
    `).join('');

    observeReveals();
  } catch (error) {
    showApiError(error);
  }
}

async function backendLoadJournal() {
  try {
    const result = await api('journal');
    const entries = result.entries;
    const container = $('journalChart');

    if (!entries.length) {
      container.innerHTML =
        '<div class="journal-empty">هنوز چیزی ثبت نکردی.<br>با یک جمله‌ی کوچک شروع کن 🌱</div>';
      return;
    }

    const last14 = entries.slice(-14);
    container.innerHTML = `
      <div class="journal-chart">
        ${last14.map(entry => {
          const label = new Intl.DateTimeFormat('fa-IR', {
            day: 'numeric',
            month: 'short'
          }).format(new Date(entry.date));
          const height = Math.max(20, Math.min(100, entry.text.length + 20));

          return `<div class="jbar" style="height:${height}%"
            data-tip="${safeHTML(label + ': ' + entry.text.slice(0, 20))}"></div>`;
        }).join('')}
      </div>
      <div class="journal-stats">
        <span>${toFa(last14.length)} ثبت اخیر</span>
        <span>${toFa(entries.length)} کل ثبت</span>
      </div>
      <div style="margin-top:12px;font-size:12px;color:#8a7565;line-height:1.9">
        آخرین ثبت: «${safeHTML(entries[entries.length - 1].text)}»
      </div>
    `;
  } catch (error) {
    $('journalChart').innerHTML =
      '<div class="journal-empty">دریافت ژورنال ممکن نشد.</div>';
    showApiError(error);
  }
}

async function backendAddJournal() {
  const text = $('journalInput').value.trim();
  if (text.length < 2) {
    toast('یک جمله کوچک بنویس 🌿');
    return;
  }

  try {
    await api('journal_add', { text });
    $('journalInput').value = '';
    await backendLoadJournal();
    toast('📝 ثبت شد.');
  } catch (error) {
    showApiError(error);
  }
}

async function backendAddWall() {
  const text = $('wallInput').value.trim();
  if (text.length < 3) {
    toast('یک جمله‌ی کوتاه بنویس 🌿');
    return;
  }

  try {
    await api('wall_add', { text });
    $('wallInput').value = '';
    toast('پیام ثبت شد و پس از بازبینی نمایش داده می‌شود.');
  } catch (error) {
    showApiError(error);
  }
}

async function backendSendLetter() {
  const name = $('letterName').value.trim();
  const email = $('letterEmail').value.trim();
  const text = $('letterText').value.trim();

  if (text.length < 10) {
    toast('لطفاً چند جمله بیشتر بنوی ✉️');
    return;
  }

  try {
    await api('letter', { name, email, text });
    $('letterName').value = '';
    $('letterEmail').value = '';
    $('letterText').value = '';
    toast('✉️ نامه در سامانه ذخیره شد.');
  } catch (error) {
    showApiError(error);
  }
}

async function backendConfirmBooking() {
  const name = $('bName').value.trim();
  const phone = $('bPhone').value.trim();
  const note = $('bNote').value.trim();

  if (name.length < 3) return bErr('err3', 'لطفاً نام و نام خانوادگی را کامل وارد کنید.');
  if (!/^09\d{9}$/.test(phone)) {
    return bErr('err3', 'شماره موبایل باید ۱۱ رقم و با ۰۹ شروع شود.');
  }

  try {
    const result = await api('booking', {
      therapist_id: booking.therapistId,
      mode: booking.mode,
      date: booking.date,
      time: booking.time,
      name,
      phone,
      note
    });

    $('bTrack').textContent = toFa(result.tracking_code);
    $('bSuccess').innerHTML = `
      <div><b>کد رهگیری</b><span>${toFa(result.tracking_code)}</span></div>
      <div><b>درمانگر</b><span>${safeHTML(result.therapist_name)}</span></div>
      <div><b>نوع جلسه</b><span>${safeHTML(booking.mode)}</span></div>
      <div><b>تاریخ و ساعت</b><span>${safeHTML(booking.date)} — ${safeHTML(booking.time)}</span></div>
      <div><b>نام</b><span>${safeHTML(name)}</span></div>
      <div><b>موبایل</b><span>${safeHTML(phone)}</span></div>
      <div class="total"><b>هزینه جلسه</b><span>${money(result.price)}</span></div>
    `;

    setStep(4);
    fireConfetti();
    toast('✅ نوبت در سامانه ثبت شد');
  } catch (error) {
    bErr('err3', error.message);
  }
}

async function backendConfirmWorkshop() {
  const name = $('wName').value.trim();
  const phone = $('wPhone').value.trim();
  const errorBox = $('wErr');

  if (name.length < 3) {
    errorBox.textContent = 'لطفاً نام و نام خانوادگی را کامل وارد کنید.';
    errorBox.classList.add('show');
    return;
  }
  if (!/^09\d{9}$/.test(phone)) {
    errorBox.textContent = 'شماره موبایل باید ۱۱ رقم و با ۰۹ شروع شود.';
    errorBox.classList.add('show');
    return;
  }

  try {
    const result = await api('workshop', {
      workshop_id: currentWorkshop.id,
      name,
      phone
    });

    errorBox.classList.remove('show');
    $('wTrack').textContent = toFa(result.tracking_code);
    $('wSuccess').innerHTML = `
      <div><b>کد ثبت‌نام</b><span>${toFa(result.tracking_code)}</span></div>
      <div><b>کارگاه</b><span>${safeHTML(result.workshop_title)}</span></div>
      <div><b>مدرس</b><span>${safeHTML(result.instructor)}</span></div>
      <div><b>تاریخ شروع</b><span>${safeHTML(result.start_date)}</span></div>
      <div><b>نام</b><span>${safeHTML(name)}</span></div>
      <div><b>موبایل</b><span>${safeHTML(phone)}</span></div>
      <div class="total"><b>هزینه</b><span>${money(result.price)}</span></div>
    `;

    closeModal('mWorkshop');
    openModal('mWorkshopOk');
    fireConfetti();
    toast('🎓 ثبت‌نام شما در کارگاه انجام شد');
  } catch (error) {
    errorBox.textContent = error.message;
    errorBox.classList.add('show');
  }
}

async function backendSetMood(mood, skipped = false) {
  try {
    await api('state_set', {
      mood: mood || '',
      mood_seen: true,
      mood_skipped: skipped
    });

    if (mood && moodThemes[mood]) {
      currentMood = mood;
      applyMoodTheme(mood);
      hideMoodPicker();
      toast('🌿 فضای سایت با حال امروزت هماهنگ شد');
    } else {
      hideMoodPicker();
    }
  } catch (error) {
    showApiError(error);
  }
}

/*
 * این شنونده قبل از onclickهای داخل HTML اجرا می‌شود.
 * به این ترتیب ذخیره‌سازی قدیمی localStorage برای این فرم‌ها اجرا نمی‌شود.
 */
document.addEventListener('click', event => {
  const button = event.target.closest('button');
  if (!button) return;

  const onclick = button.getAttribute('onclick') || '';
  let action = null;

  if (onclick.includes('confirmBooking()')) action = backendConfirmBooking;
  else if (onclick.includes('confirmWorkshop()')) action = backendConfirmWorkshop;
  else if (onclick.includes('addJournalEntry()')) action = backendAddJournal;
  else if (onclick.includes('addWallNote()')) action = backendAddWall;
  else if (onclick.includes('sendLetter()')) action = backendSendLetter;
  else if (onclick.includes('selectMood(')) {
    const match = onclick.match(/selectMood\('([^']+)'\)/);
    if (match) action = () => backendSetMood(match[1]);
  } else if (onclick.includes('skipMood()')) {
    action = () => backendSetMood('', true);
  }

  if (action) {
    event.preventDefault();
    event.stopImmediatePropagation();
    action();
  }
}, true);

async function backendInitialize() {
  await Promise.all([backendLoadWall(), backendLoadJournal()]);

  try {
    const result = await api('state');
    const state = result.state || {};
    const mood = state.mood || '';

    if (mood && moodThemes[mood]) {
      currentMood = mood;
      applyMoodTheme(mood);
    }

    if (!Number(state.mood_seen) && !Number(state.mood_skipped)) {
      window.addEventListener('load', () => {
        setTimeout(() => {
          showMoodPicker();
          api('state_set', {
            mood: '',
            mood_seen: true,
            mood_skipped: false
          }).catch(() => {});
        }, 800);
      });
    }
  } catch (error) {
    console.error('Backend initialization error:', error);
    showApiError(error);
  }
}

backendInitialize();