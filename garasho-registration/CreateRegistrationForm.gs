/**
 * GARASHO – Kulanka 1aad: Foomka Diiwaangelinta
 *
 * Wuxuu si toos ah u abuuraa Google Form-ka diiwaangelinta, wuxuuna ku xiraa
 * Google Sheet jawaabaha lagu kaydiyo.
 *
 * Isticmaalka:
 *   1. Fur https://script.google.com  →  New project
 *   2. Koobi oo ku dheji koodhkan, kadib Save.
 *   3. Dooro function-ka `createGarashoRegistrationForm` → Run (oggolow permissions-ka).
 *   4. Fur View → Logs (ama Execution log): halkaas ka qaado linkiga la wadaagayo.
 */

const EVENT = {
  title: 'GARASHO – Diiwaangelinta Kulanka 1aad',
  theme: 'Yaa Dhisa Garashada Bulshada?',
  date: 'Arbaco, 28 Oktoobar 2026',
  time: '07:30 – 09:00 fiidnimo (bilowga rasmiga ah: 08:00)',
  venue: 'Muqdisho, Soomaaliya (goobta rasmiga ah waa la xaqiijin doonaa)',
};

function createGarashoRegistrationForm() {
  const form = FormApp.create(EVENT.title);

  form
    .setDescription(
      'Wada-hadal dhaliya isbeddel.  •  Faith • Wisdom • Character • Action\n\n' +
      'Mawduuca: ' + EVENT.theme + '\n' +
      'Taariikhda: ' + EVENT.date + '\n' +
      'Wakhtiga: ' + EVENT.time + '\n' +
      'Goobta: ' + EVENT.venue + '\n\n' +
      'Fadlan horay isu diiwaangeli si aan kuugu diyaarinno boos. ' +
      'Foomkani wuxuu qaadanayaa 2 daqiiqo.'
    )
    .setConfirmationMessage(
      'Mahadsanid! Diiwaangelintaada waa la helay. ' +
      'Waxaan kugula soo xiriiri doonnaa faahfaahinta goobta iyo xasuusin ka hor kulanka.\n\n' +
      'كُنْ صَوْتًا لِلْخَيْرِ  —  Noqo Codka Wanaagga.'
    )
    .setProgressBar(false)
    .setShowLinkToRespondAgain(false)
    .setAllowResponseEdits(false)
    .setCollectEmail(false);

  // --- Xogta qofka ---
  form.addTextItem()
    .setTitle('Magaca oo buuxa')
    .setRequired(true);

  form.addTextItem()
    .setTitle('Lambarka taleefanka / WhatsApp')
    .setHelpText('Tusaale: +252 61 1234567')
    .setRequired(true)
    .setValidation(
      FormApp.createTextValidation()
        .setHelpText('Fadlan geli lambar sax ah (ugu yaraan 7 god).')
        .requireTextMatchesPattern('^\\+?[0-9 ()-]{7,20}$')
        .build()
    );

  form.addTextItem()
    .setTitle('Email (ikhtiyaari)')
    .setValidation(
      FormApp.createTextValidation()
        .setHelpText('Fadlan geli email sax ah.')
        .requireTextIsEmail()
        .build()
    );

  form.addMultipleChoiceItem()
    .setTitle('Jinsiga')
    .setChoiceValues(['Lab', 'Dheddig'])
    .setRequired(true);

  form.addMultipleChoiceItem()
    .setTitle("Da'da")
    .setChoiceValues(['Ka yar 18', '18 – 24', '25 – 34', '35 – 44', '45 iyo ka weyn'])
    .setRequired(true);

  form.addTextItem()
    .setTitle('Magaalada / Degmada aad ku nooshahay')
    .setRequired(true);

  form.addMultipleChoiceItem()
    .setTitle('Xaaladdaada hadda')
    .setChoiceValues(['Arday', 'Shaqaale', 'Ganacsade', 'Macallin / Aqoonyahan', 'Content creator / Warbaahin'])
    .showOtherOption(true)
    .setRequired(true);

  // --- Kulanka ---
  form.addCheckboxItem()
    .setTitle('Sidee ku maqashay kulanka GARASHO?')
    .setChoiceValues(['Facebook', 'TikTok', 'YouTube', 'WhatsApp', 'Saaxiib / Qoys'])
    .showOtherOption(true);

  form.addParagraphTextItem()
    .setTitle('Su\'aal aad jeclaan lahayd in martida la weydiiyo (ikhtiyaari)')
    .setHelpText(
      'Mawduuca: "' + EVENT.theme + '" – Sidee codadka wanaagsan uga dhigi karnaa ' +
      'kuwo muuqda oo saameyn leh? Su\'aalaha qaarkood ayaa la soo xulan doonaa kulanka.'
    );

  form.addMultipleChoiceItem()
    .setTitle('Ma jeclaan lahayd inaad ka qayb qaadato "7 Maalmood oo Codka Wanaagga ah"?')
    .setHelpText(
      'Toddobaad gudihiis la wadaag ugu yaraan hal farriin ama muuqaal waxtar leh ' +
      '(akhlaaq, aqoon, qoys, xirfad ama masuuliyadda digital-ka).'
    )
    .setChoiceValues(['Haa', 'Maya', 'Waan ka fikiri doonaa']);

  // --- Oggolaansho ---
  form.addCheckboxItem()
    .setTitle('Oggolaansho')
    .setHelpText(
      'Kulanka waa la duubi doonaa, sawirrona waa laga qaadi doonaa. ' +
      'Waxaan sidoo kale ballan qaadayaa inaan ixtiraamo xeerarka wada-hadalka ' +
      '(fikirka ayaa laga doodayaa, qofka lama weerarayo).'
    )
    .setChoiceValues(['Waan akhriyey, waana oggolahay'])
    .setRequired(true);

  // Jawaabaha ku kaydi Google Sheet cusub
  const sheet = SpreadsheetApp.create(EVENT.title + ' (Jawaabaha)');
  form.setDestination(FormApp.DestinationType.SPREADSHEET, sheet.getId());

  Logger.log('Linkiga la wadaagayo (participants): ' + form.getPublishedUrl());
  Logger.log('Wax ka beddelka foomka (edit):      ' + form.getEditUrl());
  Logger.log('Jawaabaha (Google Sheet):           ' + sheet.getUrl());
}
