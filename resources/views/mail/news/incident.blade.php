{{--
   Служебное письмо о сбое у хостинга (кибератака на Beget, ноябрь 2026).
   Шлёт команда news:incident. Текст — тот же, что в объявлении на сайте
   (partials/site-notice), плюс извинения и «работаем в прежнем режиме».

   Без скидок и призывов купить: это письмо о работе сервиса, не реклама.
   Вёрстка таблицами и стилями в атрибутах — как в mail/news/update.
--}}
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="color-scheme" content="light">
  <meta name="supported-color-schemes" content="light">
  <title>Слайдуша снова работает</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f1eb; font-family:Arial,Helvetica,sans-serif; color:#15161a;">

  <!-- Прехедер: строка рядом с темой в списке писем -->
  <div style="display:none; font-size:1px; color:#f4f1eb; line-height:1px; max-height:0; max-width:0; opacity:0; overflow:hidden;">
    В ночь на 2 ноября наш хостинг-провайдер пострадал от кибератаки. Рассказываем, что это значит для вас.
  </div>

  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f4f1eb;">
    <tr>
      <td align="center" style="padding:32px 12px;">

        <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0"
          style="width:100%; max-width:600px; background-color:#ffffff; border-radius:20px; overflow:hidden;">

          <!-- Логотип -->
          <tr>
            <td align="center" style="padding:30px 24px 10px;">
              <div style="font-size:24px; line-height:32px; font-weight:700; color:#15161a;">
                <span style="color:#b65229;">&#10022;</span> Слайдуша
              </div>
            </td>
          </tr>

          <!-- Заголовок -->
          <tr>
            <td align="center" style="padding:18px 32px 8px;">
              <div style="display:inline-block; padding:6px 13px; background-color:#faf0eb; border-radius:20px; color:#903e1d; font-size:12px; font-weight:700; letter-spacing:0.5px;">
                ВАЖНО О РАБОТЕ СЕРВИСА
              </div>
              <h1 style="margin:20px 0 12px; font-size:32px; line-height:40px; font-weight:700; color:#15161a;">
                Слайдуша снова работает
              </h1>
              <p style="margin:0; font-size:17px; line-height:27px; color:#6f6b62;">
                Рассказываем, что случилось и&nbsp;что делать, если у&nbsp;вас что-то пропало.
              </p>
            </td>
          </tr>

          <!-- Что случилось -->
          <tr>
            <td style="padding:24px 32px 8px;">
              <p style="margin:0 0 14px; font-size:15px; line-height:25px; color:#3d3a34;">
                Здравствуйте!
              </p>
              <p style="margin:0 0 14px; font-size:15px; line-height:25px; color:#3d3a34;">
                Мы хранили данные у&nbsp;хостинг-провайдера Beget. В&nbsp;ночь на&nbsp;2&nbsp;ноября на&nbsp;него обрушилась масштабная кибератака, и&nbsp;часть наших данных была утеряна. Несколько дней Слайдуша была недоступна.
              </p>
              <p style="margin:0 0 14px; font-size:15px; line-height:25px; color:#3d3a34;">
                Приносим свои извинения. Нам очень жаль, что вы&nbsp;столкнулись с&nbsp;этим, особенно если в&nbsp;эти дни вам нужна была презентация.
              </p>
              <p style="margin:0; font-size:15px; line-height:25px; color:#3d3a34;">
                Мы перенесли сервис на&nbsp;новый сервер и&nbsp;восстановили данные. Сейчас Слайдуша работает в&nbsp;прежнем режиме.
              </p>
            </td>
          </tr>

          <!-- Если что-то не так -->
          <tr>
            <td style="padding:18px 32px 0;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#faf0eb; border-radius:14px;">
                <tr>
                  <td style="padding:20px 22px 8px;">
                    <div style="font-size:17px; line-height:24px; font-weight:700; color:#15161a;">
                      Если что-то не&nbsp;так&nbsp;— напишите нам
                    </div>
                  </td>
                </tr>
                <tr>
                  <td style="padding:6px 22px 20px;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                      <tr>
                        <td width="18" valign="top" style="font-size:15px; line-height:24px; color:#b65229; font-weight:700;">&bull;</td>
                        <td valign="top" style="padding:0 0 10px; font-size:15px; line-height:24px; color:#3d3a34;">
                          Пропала оплата или презентация&nbsp;— обязательно напишите, мы&nbsp;начислим генерации.
                        </td>
                      </tr>
                      <tr>
                        <td width="18" valign="top" style="font-size:15px; line-height:24px; color:#b65229; font-weight:700;">&bull;</td>
                        <td valign="top" style="padding:0 0 10px; font-size:15px; line-height:24px; color:#3d3a34;">
                          Что-то до&nbsp;сих пор не&nbsp;работает&nbsp;— тоже напишите, разберёмся и&nbsp;починим.
                        </td>
                      </tr>
                      <tr>
                        <td width="18" valign="top" style="font-size:15px; line-height:24px; color:#b65229; font-weight:700;">&bull;</td>
                        <td valign="top" style="font-size:15px; line-height:24px; color:#3d3a34;">
                          С&nbsp;вопросами, возвратом денег или по&nbsp;другой причине пишите в&nbsp;чат в&nbsp;личном кабинете.
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Кнопка -->
          <tr>
            <td align="center" style="padding:30px 24px 10px;">
              <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                <tr>
                  <td align="center" bgcolor="#15161a" style="border-radius:11px;">
                    <a href="{{ $siteUrl }}" target="_blank"
                      style="display:inline-block; padding:15px 30px; font-size:15px; line-height:20px; font-weight:700; color:#ffffff; text-decoration:none; border-radius:11px;">
                      Открыть Слайдушу
                    </a>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Прощание -->
          <tr>
            <td align="center" style="padding:22px 32px 32px;">
              <p style="margin:0 0 8px; font-size:15px; line-height:24px; color:#3d3a34;">
                Спасибо, что остаётесь с&nbsp;нами.
              </p>
              <p style="margin:0; font-size:14px; line-height:23px; color:#6f6b62;">
                Чат&nbsp;— кнопка в&nbsp;правом нижнем углу личного кабинета.
              </p>
              <p style="margin:18px 0 0; font-size:14px; line-height:22px; font-weight:700; color:#b65229;">
                Команда Слайдуши
              </p>
            </td>
          </tr>
        </table>

        <!-- Подвал -->
        <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="width:100%; max-width:600px;">
          <tr>
            <td align="center" style="padding:22px 20px 6px; font-size:11px; line-height:18px; color:#8a857a;">
              Это письмо о работе сервиса: вы зарегистрированы на&nbsp;{{ $site }}.<br>
              {{ $legalName }}, ИНН {{ $legalInn }}.
            </td>
          </tr>
          <tr>
            <td align="center" style="padding:0 20px 20px; font-size:11px; line-height:18px;">
              <a href="{{ $unsubscribeUrl }}" style="color:#6f6b62; text-decoration:underline;">Не получать такие письма</a>
            </td>
          </tr>
        </table>

      </td>
    </tr>
  </table>

</body>
</html>
