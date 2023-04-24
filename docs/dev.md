- [Development environment](#development-environment)
- [Public API](#public-api)
  - [How to connect](#how-to-connect)
    - [First method: Share Link](#first-method-share-link)
      - [Get the `<project_token>`](#get-the-project_token)
      - [Get the `<project_password>`](#get-the-project_password)
      - [Trying](#trying)
    - [Second method: App Password (UI)](#second-method-app-password-ui)
      - [Obtaining the credentials](#obtaining-the-credentials)
      - [Using the credentials](#using-the-credentials)
      - [Trying](#trying-1)
  - [Specifications](#specifications)
    - [Additional details](#additional-details)
      - [A note about errors](#a-note-about-errors)
      - [A note about access levels](#a-note-about-access-levels)
      - [Autoexport](#autoexport)
      - [Sort](#sort)
      - [Repeat](#repeat)
      - [Payment Modes' ID](#payment-modes-id)
    - [Ping](#ping)
    - [Create Project](#create-project)
    - [Get Project Info](#get-project-info)
    - [Set Project Info](#set-project-info)
    - [Delete project](#delete-project)
    - [Get Members](#get-members)
    - [Add Member](#add-member)
    - [Add Member V2](#add-member-v2)
    - [Edit Member](#edit-member)
    - [Delete Member](#delete-member)
    - [Get Bills](#get-bills)
    - [Get Bills V2](#get-bills-v2)
    - [Get Bills V3](#get-bills-v3)
    - [Add Bill](#add-bill)
    - [Edit Bill](#edit-bill)
    - [Delete Bill](#delete-bill)
    - [Get Project Statistics](#get-project-statistics)
    - [Get Project Settlement](#get-project-settlement)
    - [Auto Settlement](#auto-settlement)
    - [Add Currency](#add-currency)
    - [Edit Currency](#edit-currency)
    - [Delete Currency](#delete-currency)
    - [Add Payment Mode](#add-payment-mode)
    - [Edit Payment Mode](#edit-payment-mode)
    - [Delete Payment Mode](#delete-payment-mode)
    - [Add Category](#add-category)
    - [Edit Category](#edit-category)
    - [Delete Category](#delete-category)

# Development environment

Clone this repository and build:

``` bash
cd /var/www/.../nextcloud/apps
git clone https://github.com/eneiluj/cospend-nc cospend
cd cospend
npm ci
npm run watch
```

Or if you want to use HMR (hot module replacement),
install the [Nextcloud HMR Enabler app](https://github.com/nextcloud/hmr_enabler)
and run this in cospend directory:
``` bash
npm run serve
```

# Public API

The root URL is `https://mynextcloud.org/index.php/apps/cospend`. Replace `mynextcloud.org` by your actual domain.

Plan was to make Cospend public API strictly identical to [IHateMoney API](https://ihatemoney.readthedocs.io/en/latest/api.html) but there is a restriction i couldn't bypass : the authentication system. IHateMoney uses the basic HTTP authentication, just like Nextcloud user authentication. So, to get a guest access to a Cospend project, this type of authentication was first rejected by Nextcloud user auth system and then accepted by Cospend with a huge latency.

So the most noticeable differences between IHateMoney API and Cospend API are :

* The password has to be included in the URL path, just after the project ID, like that : `https://mynextcloud.org/index.php/apps/cospend/api/myproject/projectPassword/bills`
* The parameter `payed_for` cannot be given multiple times like in IHateMoney. It has to be given once with coma separated values.


## How to connect
First and foremost, understand how to connect and get your first command working. There are two to three components you want to get:
* The project's ID `<project_id>` OR token `<project_token>`
* The project's password: `<project_password>`
* The account's credentials (if you're not using the share link method): `<username>`, `<token>`, `<password>`,... depending on the method used.

To correctly test your API for the first time, make sure you have one or two transactions entered through the web UI, otherwise you will get an empty response body despite the configuration being correct.

### First method: Share Link

This assumes you already have a project created, and want to use your API on specifically this project. This is basically a guest access to a project created beforehand. It doesn't depend on a specific account.

If you use this method, your API path will have the following shape: `<root_url>/api/projects/<project_token>/<project_password>/<command>`
#### Get the `<project_token>`



Go to the project settings in the UI, open the Sharing tab, and create a share link. Your link should look something like this:
`https://mynextcloud.org/apps/cospend/s/ba3355eaecc6254ad1755fa8e7cdf54a)`

Take note of the project's token (`ba3355eaecc6254ad1755fa8e7cdf54a`), that will act as your `<project_token>`.
#### Get the `<project_password>`
If you chose to set a password on your link, use it as your `<project_password>`.

**If you don't set a password on your share link, there will be a default password**: The default password is `no-pass`. It's not required when using the web view, but it is with the API, as explained earlier.
#### Trying
You can now try your first `<command>`: `/statistics`. If you've followed correctly, your request should look like this:

```bash
curl -s https://mynextcloud.org/index.php/apps/cospend/api/projects/ba3355eaecc6254ad1755fa8e7cdf54a/no-pass/statistics
```
and have a correct result:
<details>

```json
{
  "stats": [
    {
      "balance": -119,
      "filtered_balance": -119,
      "paid": 0,
      "spent": 119,
      "member": {
        "activated": true,
        "userid": "firstuserid",
        "name": "John Do",
        "id": 4,
        "weight": 1,
        "color": {
          "r": 110,
          "g": 166,
          "b": 143
        },
        "lastchanged": 1679234081
      }
    },
    {
      "balance": 119,
      "filtered_balance": 119,
      "paid": 520,
      "spent": 401,
      "member": {
        "activated": true,
        "userid": "seconduserid",
        "name": "Alice Foo",
        "id": 3,
        "weight": 1,
        "color": {
          "r": 0,
          "g": 130,
          "b": 201
        },
        "lastchanged": 1679234078
      }
    }
  ],
  "memberMonthlyPaidStats": {
    "2023-03": {
      "4": 0,
      "3": 520,
      "0": 520
    },
    "Average per month": {
      "4": 0,
      "3": 520,
      "0": 520
    }
  },
  "memberMonthlySpentStats": {
    "2023-03": {
      "4": 119,
      "3": 401,
      "0": 520
    },
    "Average per month": {
      "4": 119,
      "3": 401,
      "0": 520
    }
  },
  "categoryStats": [
    520
  ],
  "categoryMonthlyStats": [
    {
      "2023-03": 520,
      "Average per month": 520
    }
  ],
  "paymentModeStats": [
    520
  ],
  "paymentModeMonthlyStats": [
    {
      "2023-03": 520,
      "Average per month": 520
    }
  ],
  "categoryMemberStats": [
    {
      "4": 0,
      "3": 520
    }
  ],
  "memberIds": [
    4,
    3
  ],
  "allMemberIds": [
    4,
    3
  ],
  "membersPaidFor": {
    "4": {
      "4": 0,
      "3": 0,
      "total": 0
    },
    "total": {
      "4": 119,
      "3": 401
    },
    "3": {
      "4": 119,
      "3": 401,
      "total": 520
    }
  },
  "realMonths": [
    "2023-03"
  ]
}
```

</details>


### Second method: App Password (UI)
This second method gives a larger access to your account, not just to cospend or to a single cospend's project.

#### Obtaining the credentials
First of all, you need a **password**. Open your Nextcloud UI, visit your personal settings, under Security (https://mynextcloud.org/settings/user/security). Under _Devices & Sessions_, create a new app password. Make good note of the given password (a series of alphanumericals separated by dashes). This is your `<app_password>`.

The next bit of information is your Nextcloud's **username** (used to login, not your profile name neither your email address). This is your `<username>`

To work on a project, you will also need its **ID**. Note that this is different than the `<project_token>` used earlier. Here, we don't need to create a guest access to a project. The project ID is the name of the project, in lower case, with spaces replaced by dashes. When you click on the project in the web UI, you will see the ID in the address bar: `https://mynextcloud.org/apps/cospend/p/<project_id>`

If you use this method, your API path will have the following shape: `<root_url>/api-priv/projects/<project_id>/<command>`

**Do note:**
* This URL starts with `api-priv` instead of `api`. This is for logged in clients, that don't require a project's password.
* There's no more password on the project
#### Using the credentials
This method uses [HTTP Basic Authentication](https://developer.mozilla.org/en-US/docs/Web/HTTP/Authentication#basic_authentication_scheme). Most libraries and languages offer an easy method for that (e.g. `curl -u "<username>:<app_password>" <url>`). If you need to do it manually:
* Prepare the string
* Base64 encode it
* Pass the result to a header `Authorization: Basic `

For instance:
```bash
curl -u "johndoe:mypassword" https://example.com
# equivalent to:
encoded=$(echo -n "johndoe:mypassword" | base64) # -n mandatory, otherwise \n is also encoded
curl -H "Authorization: Basic $encoded" https://example.com
```

In order for the header to be correctly interpreted, you also need an additional header: `OCS-APIRequest: true`. For instance:
```
curl -H "OCS-APIRequest: true" -u "<username>:<app_password>" <url>
```
#### Trying
You can now try your first `<command>`: `/statistics`. If you've followed correctly, your request should look like this:

```bash
curl -s -u "<username>:<app_password" -H "OCS-APIRequest: true" \
https://mynextcloud.org/index.php/apps/cospend/api/projects/<project_id>/statistics
```
And obtain the same result as before.



## Specifications
Now that you've managed to land your first succesful request, let's dive into the concrete API specification.

As explained above, depending on your authentication method, the endpoint will look slightly different.
The core part of the endpoint (`/api/projects/<project_token>/<project_password>` for anonymous, and `/api-priv/projects/<project_id>` for logged in) will be substituted by `<base_endpoint>`. If `<base_endpoint>` is not mentioned, the endpoint is relative to the root url.

**Examples**:
|        Endpoint         | Full URL(s)                                                                                                                                                                                         |
| :---------------------: | :-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
|       `/api/ping`       | `https://mynextcloud.org/index.php/apps/cospend/api/ping`                                                                                                                                           |
| `<base_endpoint>/bills` | *`https://mynextcloud.org/index.php/apps/cospend/api/projects/<project_token>/<project_password>/bills` <br> *`https://mynextcloud.org/index.php/apps/cospend/api-priv/projects/<project_id>/bills` |


| Name                                               | Endpoint                                                      | Method | Anonymous/Logged |
| -------------------------------------------------- | :------------------------------------------------------------ | :----- | ---------------- |
| [Ping                  ](#ping                   ) | `/api/ping`                                                   | GET    | **Logged**       |
| [Create Project        ](#create-project         ) | `/api/projects` (anonymous),<br>`/api-priv/projects` (logged) | POST   | Anonymous/Logged |
| [Get Project Info      ](#get-project-info       ) | `<base_endpoint>/`                                            | GET    | Anonymous/Logged |
| [Set Project Info      ](#set-project-info       ) | `<base_endpoint>/`                                            | PUT    | Anonymous/Logged |
| [Delete project        ](#delete-project         ) | `<base_endpoint>/`                                            | DELETE | Anonymous/Logged |
| [Get Members           ](#get-members            ) | `<base_endpoint>/members`                                     | GET    | Anonymous/Logged |
| [Add Member            ](#add-member             ) | `<base_endpoint>/members`                                     | POST   | Anonymous/Logged |
| [Add Member V2         ](#add-member-v2          ) | `/apiv2/projects/<project_token>/<project_password>/members`  | POST   | **Anonymous**    |
| [Edit Member           ](#edit-member            ) | `<base_endpoint>/members/<member_id>`                         | PUT    | Anonymous/Logged |
| [Delete Member         ](#delete-member          ) | `<base_endpoint>/members/<member_id>`                         | DELETE | Anonymous/Logged |
| [Get Bills             ](#get-bills              ) | `<base_endpoint>/bills`                                       | GET    | Anonymous/Logged |
| [Get Bills V2          ](#get-bills-v2           ) | `/apiv2/projects/<project_token>/<project_password>/bills`    | GET    | **Anonymous**    |
| [Get Bills V3          ](#get-bills-v3           ) | `/apiv3/projects/<project_token>/<project_password>/bills`    | GET    | **Anonymous**    |
| [Add Bill              ](#add-bill               ) | `<base_endpoint>/bills`                                       | POST   | Anonymous/Logged |
| [Edit Bill             ](#edit-bill              ) | `<base_endpoint>/bills/<bill_id>`                             | PUT    | Anonymous/Logged |
| [Delete Bill           ](#delete-bill            ) | `<base_endpoint>/bills/<bill_id>`                             | DELETE | Anonymous/Logged |
| [Get Project Statistics](#get-project-statistics ) | `<base_endpoint>/statistics`                                  | GET    | Anonymous/Logged |
| [Get Project Settlement](#get-project-settlement ) | `<base_endpoint>/settle`                                      | GET    | Anonymous/Logged |
| [Auto Settlement       ](#auto-settlement        ) | `<base_endpoint>/autosettlement`                              | GET    | Anonymous/Logged |
| [Add Currency          ](#add-currency           ) | `<base_endpoint>/currency`                                    | POST   | Anonymous/Logged |
| [Edit Currency         ](#edit-currency          ) | `<base_endpoint>/currency/<currency_id>`                      | PUT    | Anonymous/Logged |
| [Delete Currency       ](#delete-currency        ) | `<base_endpoint>/currency/<currency_id>`                      | DELETE | Anonymous/Logged |
| [Add Payment Mode      ](#add-payment-mode       ) | `<base_endpoint>/paymentmode`                                 | POST   | Anonymous/Logged |
| [Edit Payment Mode     ](#edit-payment-mode      ) | `<base_endpoint>/paymentmode/<pm_id>`                         | PUT    | Anonymous/Logged |
| [Delete Payment Mode   ](#delete-payment-mode    ) | `<base_endpoint>/paymentmode/<pm_id>`                         | DELETE | Anonymous/Logged |
| [Add Category          ](#add-category           ) | `<base_endpoint>/category/`                                   | POST   | Anonymous/Logged |
| [Edit Category         ](#edit-category          ) | `<base_endpoint>/category/<category_id>`                      | PUT    | Anonymous/Logged |
| [Delete Category       ](#delete-category        ) | `<base_endpoint>/category/<category_id>`                      | DELETE | Anonymous/Logged |

### Additional details
#### A note about errors
While some errors are explicit and specific to a situation, many situations have the same error for the same behavior. In order to make the following specification more readable, non-specific errors are explained here


* Missing login for private API
  * Situation: the credentials are missing altogether, although using a logged in endpoint (`.../api-priv/...`)
  * Error message: `{"message":"CORS requires basic auth"}`
* The credentials are invalid
  * Situation: you're using a logged in endpoint, but your credentials are incorrect (althrough present)
  * Error message: `{"message":""}`
* The URL is incorrect
  * Situation: You're using anonymous or logged in requests, and the endpoint you're accessing doesn't exist, and can't exist (nb: this doesn't apply if e.g. the project name is incorrect). This happens a lot when debugging.
  * Example: `curl  -u 'johndoe:password' https://mynextcloud.org/index.php/apps/cospend/api/pingdawd` <--
  *  _No error message_. Only a line-feed (`\n`) character is returned.

#### A note about access levels
Users or tokens can have an access level, described by an integer from 1 to 4. They are defined as the following:
<!--FIXME precise levels? -->
* 1: Viewer, can view everything (read-only access)
* 2: Participant, can read and write bills, as a normal participant
* 3: Maintainer,
* 4: Admin,

#### Autoexport
Autoexport is defined by a single character, with the following matches:
* `n`: No
* `d`: Daily
* `w`: Weekly
* `m`: Monthly

#### Sort
Categories and Payment Modes can be sorted. A single character characterizes the method, with the following matches:
* `a`: Alphabetical,
* `m`: Manual,
* `u`: Most Used,
* `r`: Most Recently used,

#### Repeat
Bills can be set to be repeating/recurring. Check the [user guide](./user.md#repeating-bills) for additional information of what each field actually  does. The `repeat` field is again defined by a single character, with the following matches:
* `n`: never
* `d`: daily (every X day)
* `w`: weekly (every X weeks, every 7*X days)
* `b`: bi-weekly (every 2 weeks, every 14 days)
* `s`: semi-monthly (twice a month, on the 1st and 15th of each month)
* `m`: monthly (every X months)
* `y`: yearly (every X years)

The field `repeatfreq` is the modifier for the repeat. It's an integer, that in practice replaces the X in the above matches. It is ignored for bi-weekly and semi-monthly repetitions.

`repeatuntil` is simply a date, defined as a Unix timestamp.  For a bill that is set to repeat, at each cron run, the current "computed next run" for the bill is compared to the `repeatuntil` field.

`repeatallactive` determines if the bill should just copy the set list of owers (False) or all current active members as the new owers (True). Note that this is not a boolean, but an integer. A value of 1 indicates True/ticked (repeat using all active members), while _any other value_ indicates False/unticked (only use the same list of owers). Default False value set by the web interface is 0. If you set a different value through the API (e.g. 5), the same value will be given back upon query. For obvious reasons it is **not recommmended** to use other values than 0 and 1.



#### Payment Modes' ID
Payment modes are defined by two ID: `id`, which is an integer, unique across all projects of this NC instance, and `old_id`, which is a single character.

While `id` is faily standard, `old_id` requires a bit more attention. It is only defined for the 5 default payment modes:
* `c` is `Credit card`
* `b` is `Cash`
* `f` is `Check`
* `t` is `Transfer`
* `o` is `Online Service`.

Manually created payment modes still have a standard `id`, but their `old_id` is `null`.

There is however another small specificity. When references are made to payment modes, another letter, `n` can appear. See for example an exract from an output from [Get Bills](#get-bills-logged-in) (many fields are omitted, this is for illustration purpose):
```json
{
  "bills": [
    {
      "id": 1,
      "amount": 66,
      "what": "Yet another bill",
      "paymentmode": "n",
      "paymentmodeid": 0,
      <....>
    }
    {
      "id": 2,
      "amount": 100,
      "what": "New Bill",
      "paymentmode": "n",
      "paymentmodeid": 26,
      <....>
    }
  ]
}
```

Notice how the first bill, with ID 1, has `paymentmode="n"` and `paymentmodeid=0`. Having both of those means there are no payment mode set (`None`).
The `n` indicates there are no `old_id` for this paymment mode.

The second bill, however, has `paymentmode="n"`, as before, but `paymentmodeid=26`. In this, this shows the payment mode is a manually created payment mode (with `id` 26).

As a general rule, everything related to the `old_id` (so the `paymentmode` in the latter case) can safely be ignored.


### Ping
### Create Project
### Get Project Info
### Set Project Info
### Delete project
### Get Members
### Add Member
### Add Member V2
### Edit Member
### Delete Member
### Get Bills
### Get Bills V2
### Get Bills V3
### Add Bill
### Edit Bill
### Delete Bill
### Get Project Statistics
### Get Project Settlement
### Auto Settlement
### Add Currency
### Edit Currency
### Delete Currency
### Add Payment Mode
### Edit Payment Mode
### Delete Payment Mode
### Add Category
### Edit Category
### Delete Category