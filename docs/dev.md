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
    - [Cheatsheet/quick access](#cheatsheetquick-access)
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
    - [Get Bills (logged in)](#get-bills-logged-in)
    - [Get Bills (anonymous)](#get-bills-anonymous)
    - [Get Bills V2](#get-bills-v2)
    - [Get Bills V3](#get-bills-v3)
    - [Add Bill](#add-bill)
    - [Edit Bill](#edit-bill)
    - [Edit Bills](#edit-bills)
    - [Delete Bill](#delete-bill)
    - [Delete Bills](#delete-bills)
    - [Get Project Statistics](#get-project-statistics)
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

#### Trying
You can now try your first `<command>`: `/statistics`. If you've followed correctly, your request should look like this:

```bash
curl -s -u "<username>:<app_password" \
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

### Cheatsheet/quick access

| Name                                               | Endpoint                                                      | Method | Anonymous/Logged |
| -------------------------------------------------- | :------------------------------------------------------------ | :----- | ---------------- |
| [Ping                  ](#ping                   ) | `/api/ping`                                                   | GET    | **Logged**       |
| [Create Project        ](#create-project         ) | `/api/projects` (anonymous),<br>`/api-priv/projects` (logged) | POST   | Anonymous/Logged |
| [Get Project Info      ](#get-project-info       ) | `<base_endpoint>`                                             | GET    | Anonymous/Logged |
| [Set Project Info      ](#set-project-info       ) | `<base_endpoint>`                                             | PUT    | Anonymous/Logged |
| [Delete project        ](#delete-project         ) | `<base_endpoint>`                                             | DELETE | Anonymous/Logged |
| [Get Members           ](#get-members            ) | `<base_endpoint>/members`                                     | GET    | Anonymous/Logged |
| [Add Member            ](#add-member             ) | `<base_endpoint>/members`                                     | POST   | Anonymous/Logged |
| [Add Member V2         ](#add-member-v2          ) | `/apiv2/projects/<project_token>/<project_password>/members`  | POST   | **Anonymous**    |
| [Edit Member           ](#edit-member            ) | `<base_endpoint>/members/<member_id>`                         | PUT    | Anonymous/Logged |
| [Delete Member         ](#delete-member          ) | `<base_endpoint>/members/<member_id>`                         | DELETE | Anonymous/Logged |
| [Get Bills (logged in) ](#get-bills-logged-in    ) | `<base_endpoint>/bills`                                       | GET    | **Logged**       |
| [Get Bills (anonymous) ](#get-bills-anonymous    ) | `<base_endpoint>/bills`                                       | GET    | **Anonymous**    |
| [Get Bills V2          ](#get-bills-v2           ) | `/apiv2/projects/<project_token>/<project_password>/bills`    | GET    | **Anonymous**    |
| [Get Bills V3          ](#get-bills-v3           ) | `/apiv3/projects/<project_token>/<project_password>/bills`    | GET    | **Anonymous**    |
| [Add Bill              ](#add-bill               ) | `<base_endpoint>/bills`                                       | POST   | Anonymous/Logged |
| [Edit Bill             ](#edit-bill              ) | `<base_endpoint>/bills/<bill_id>`                             | PUT    | Anonymous/Logged |
| [Edit Bills            ](#edit-bills             ) | `<base_endpoint>/bills`                                       | PUT    | **Anonymous**    |
| [Delete Bill           ](#delete-bill            ) | `<base_endpoint>/bills/<bill_id>`                             | DELETE | Anonymous/Logged |
| [Delete Bills          ](#delete-bills           ) | `<base_endpoint>/bills`                                       | DELETE | **Anonymous**    |
| [Get Project Statistics](#get-project-statistics ) | `<base_endpoint>/statistics`                                  | GET    | Anonymous/Logged |
| [Get Project Settlement](#get-project-settlement ) | `<base_endpoint>/settle`                                      | GET    | Anonymous/Logged |
| [Auto Settlement       ](#auto-settlement        ) | `<base_endpoint>/autosettlement`                              | GET    | Anonymous/Logged |
| [Add Currency          ](#add-currency           ) | `<base_endpoint>/currency`                                    | POST   | Anonymous/Logged |
| [Edit Currency         ](#edit-currency          ) | `<base_endpoint>/currency/<currency_id>`                      | PUT    | Anonymous/Logged |
| [Delete Currency       ](#delete-currency        ) | `<base_endpoint>/currency/<currency_id>`                      | DELETE | Anonymous/Logged |
| [Add Payment Mode      ](#add-payment-mode       ) | `<base_endpoint>/paymentmode`                                 | POST   | Anonymous/Logged |
| [Edit Payment Mode     ](#edit-payment-mode      ) | `<base_endpoint>/paymentmode/<pm_id>`                         | PUT    | Anonymous/Logged |
| [Delete Payment Mode   ](#delete-payment-mode    ) | `<base_endpoint>/paymentmode/<pm_id>`                         | DELETE | Anonymous/Logged |
| [Add Category          ](#add-category           ) | `<base_endpoint>/category`                                    | POST   | Anonymous/Logged |
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
* Availability: logged requests
* Method: `GET`
* Endpoint: `/api/ping`
* Return: Name of the user making the request, in a 1-element list.
* Errors:
  * If the request is made without proper authentication, an error is returned
  * If the authentication is incorrect, an empty message is returned
* Example usage:
  ```console
  ## no proper login
  ~$ curl https://mynextcloud.org/index.php/apps/cospend/api/ping
  {"message":"Current user is not logged in"}

  ## correct usage
  ~$ curl-u "johndoe:mypassword" https://mynextcloud.org/index.php/apps/cospend/api/ping
  ["Johndoe"]
  ```
### Create Project
Create a project. To create it anonymously, the permission `allowAnonymousCreation` must be enabled (in Nextcloud's Administration settings > Misc > Cospend >  Allow guests to create projects)
* Availability: Logged and Anonymous requests
* Method: `POST`
* Endpoint: `/api/projects` (anonymous), or `/api-priv/projects` (logged in)
* Parameters:
  * `name`: Displayed name of the project (mandatory)
  * `id`: A string, unique name across the instance. Must **not** contain a forward slash (`/`) As a comparison, the web lowercases the name and replaces spaces with dashes (`My First Project` -> `my-first-project`). (mandatory)
  * `password`: A password for the project (this is the deprecated password protected access, share link password)
  * `contact_email`: A contact email for the the project (optional)
* Return: The ID of the newly created project (the same provided in `id`).
* Errors:
  * If the `id` contains a forward slash, `{"message": "Invalid project id"}` with code 400
  * If the `id` is already used, `{"message": "A project with id <id> already exists"}` with code 400
* Example usage:
  ```console
  ~$ curl -s -X POST \
    --data-urlencode 'name=My First Project'\
    --data-urlencode 'id=my-first-project'\
    -u 'johndoe:mypassword'\
    'https://mynextcloud.org/index.php/apps/cospend/api-priv/projects'

  "my-first-project"
  ```
### Get Project Info
* Availability: Logged and Anonymous requests
* Method: GET
* Endpoint: `<base_endpoint>`
* Return:
  * `name` [string]: Name of the project.
  * `contact_email` [string]: Email of the project owner.
  * `id` [string]: ID of the project (as used in the logged in endpoint).
  * `guestaccesslevel` [integer]: [Access level](#a-note-about-access-levels) for guests <!-- FIXME -->.
  * `autoexport` [string]: Is the project set to auto-export (values: `"y"` or `"n"`).
  * `currencyname` [string]: Name of the currency of the project. `null` if the currency hasn't been set.
  * `lastchanged` [integer]: Unix timestamp of the last modification of the project <!-- FIXME -->.
  * `active_members` [list]: List of active members. This is a list, each entry containing the following:
    * `activated` [boolean]: Whether this user is activated or not.
    * `userId` [string]: The user's nextcloud ID (username)
    * `name` [string]: The user's nextcloud full name.
    * `id` [integer]: The user's cospend ID in this project <!--FIXME: scope?-->
    * `weight` [integer]: The user's [weight](user.md#what-is-a-member-ok_woman-) in this project
    * `color` [object]: An object containing the RGB components (0ver 255) of the color of the user. Specifically:
      * `r` [integer]: the Red component (0-255)
      * `g` [integer]: the Green component (0-255)
      * `b` [integer]: the Blue component (0-255)
    * `lastchanged` [integer]: Unix timestamp of the last modification of the user <!--FIXME-->
  * `members` [list]: this has the same content as the `active_members` entry. The difference is <!--FIXME-->
  * `balance` [object]: An object, in which keys are the members' project integer ID, and the values are the current balance of each member (as a number, positive or negative). By definition, the sum of the balance equal 0.
  * `nb_bills` [integer]: Total number of bills in this project.
  * `total_spent` [number]: Sum of all the bills' sums.
  * `shares` [list]: List of accesses to the project. They can be of 2 types: Users or links (this is what is available in the web interface, under the project's Sharing tab.):
    * Users: an object with the following entries:
      * `userId` [string]: The user's nextcloud ID (username)
      * `name` [string]: The user's nextcloud full name
      * `id` [integer]: The user's cospend ID in this project <!--FIXME: scope?-->.
      * `accesslevel` []: The user's [access level](#a-note-about-access-levels).
      * `type` [string]: A single character `"u"`, to indicate the type of access (means "User").
      * `manually_added` [boolean]: Was this share created manually or automatically.
    * Links: an object with the following entries:
      * `token` [string]: The token used for guest access. This is the `<project_token>` explained [earlier](#get-the-project_token).
      * `id` [integer]: The link's cospend ID in this project 4.
      * `accesslevel` []: The [access level](#a-note-about-access-levels) of the link.
      * `label` [string, optional]: Label given to the link. If none is specified, `null`.
      * `password` [string, optional]: Password give to the link. If none is specified, `null`. **Remember**: if you're using the API with a link that doesn't have a password, you need to use the [default password](#get-the-project_password)
      * `type` [string]: A single character `"l"`, to indicate the type of access (means "Link").
  * `currencies` [list]: A list of all secondary currencies. If none, the list is just empty. Each entry is an object with the following entries:
    * `name` [string]: Name of the currency
    * `exhange_rate` [number]: The factor between for this currency (_1 of this currency = X of main currency_)
    * `id` [integer]: ID of the currency for this project
  * `categories` [object]: An object containing all the categories. The keys are the categories' ID. For each category, the object is the following:
    * `name` [string]: Name of the category.
    * `icon` [string]: Single unicode character containing the emoji used as the icon of the category.
    * `color` [string]: RGB string, prefixed with a pound sign, that represents the color of the category.
    * `id` [integer]: ID of the currency (this is the *same* as the key that leads to this object). **The ID is global across all your projects**. Category `"1"` is only availably in a single project for instance.
    * `order` [integer]: Order of the category, when sorting is manual
  * `paymentmodes` [object]: Payment modes, with the same format as `categories`: this is an object, the keys are the payment modes' ID, and the values are objects. The objects have the following shape:
    * `name` [string]: Name of the payment method.
    * `icon` [string]: Single unicode character containing the emoji used as the icon of the payment method.
    * `color` [string]: RGB string, prefixed with a pound sign, that represents the color of the payment method.
    * `id` [integer]: ID of the currency (this is the *same* as the key that leads to this object). **The ID is global across all your projects**. Payment method `"1"` is only availably in a single project for instance.
    * `order` [integer]: Order of the payment method, when sorting is manual.
    * `old_id` [string]: Legacy name for the ID of the payment method.
  * `deletion_disabled` [boolean]: false,
  * `categorysort` [string]: How the categories are sorted. See the [corresponding note](#sort)
  * `paymentmodesort` [string]: Similar to `categorysort`, but for payment modes. See the [corresponding note](#sort)
  * `myaccesslevel` [integer]: [Access level](#a-note-about-access-levels) of the user making the request.

* Errors:
  *
* Example usage:
  ```console
  ~$ curl -s -u 'johndoe:mypassword' https://mynextcloud.org/index.php/apps/cospend/api-priv/projects/my-first-project
  ```
  <details >
    <summary>Sample answer</summary>

    ```json
    {
      "name": "My First Project",
      "contact_email": "johndoe@mynextcloud.org",
      "id": "my-first-project",
      "guestaccesslevel": 2,
      "autoexport": "n",
      "currencyname": "EUR",
      "lastchanged": 1678648595,
      "active_members": [
        {
          "activated": true,
          "userid": "alicedoe",
          "name": "Alice Doe",
          "id": 2,
          "weight": 1,
          "color": {
            "r": 110,
            "g": 166,
            "b": 143
          },
          "lastchanged": 1678636572
        },
        {
          "activated": true,
          "userid": "johndoe",
          "name": "John Doe",
          "id": 1,
          "weight": 1,
          "color": {
            "r": 0,
            "g": 130,
            "b": 201
          },
          "lastchanged": 1678636568
        }
      ],
      "members": [
        {
          "activated": true,
          "userid": "alicedoe",
          "name": "Alice Doe",
          "id": 2,
          "weight": 1,
          "color": {
            "r": 110,
            "g": 166,
            "b": 143
          },
          "lastchanged": 1678636572
        },
        {
          "activated": true,
          "userid": "johndoe",
          "name": "John Doe",
          "id": 1,
          "weight": 1,
          "color": {
            "r": 0,
            "g": 130,
            "b": 201
          },
          "lastchanged": 1678636568
        }
      ],
      "balance": {
        "2": -83,
        "1": 83
      },
      "nb_bills": 2,
      "total_spent": 166,
      "shares": [
        {
          "userid": "alicedoe",
          "name": "Alice Doe",
          "id": 3,
          "accesslevel": 2,
          "type": "u",
          "manually_added": false
        },
        {
          "token": "bb9d1bced1d3896e6672db461753e93d",
          "id": 4,
          "accesslevel": 2,
          "label": null,
          "password": null,
          "type": "l"
        }
      ],
      "currencies": [],
      "categories": {
        "1": {
          "name": "Grocery",
          "icon": "🛒",
          "color": "#ffaa00",
          "id": 1,
          "order": 0
        },
        "2": {
          "name": "Bar/Party",
          "icon": "🎉",
          "color": "#aa55ff",
          "id": 2,
          "order": 0
        },
        "3": {
          "name": "Rent",
          "icon": "🏠",
          "color": "#da8733",
          "id": 3,
          "order": 0
        },
        "4": {
          "name": "Bill",
          "icon": "🌩",
          "color": "#4aa6b0",
          "id": 4,
          "order": 0
        },
        "5": {
          "name": "Excursion/Culture",
          "icon": "🚸",
          "color": "#0055ff",
          "id": 5,
          "order": 0
        },
        "6": {
          "name": "Health",
          "icon": "💚",
          "color": "#bf090c",
          "id": 6,
          "order": 0
        },
        "7": {
          "name": "Shopping",
          "icon": "🛍",
          "color": "#e167d1",
          "id": 7,
          "order": 0
        },
        "8": {
          "name": "Restaurant",
          "icon": "🍴",
          "color": "#d0d5e1",
          "id": 8,
          "order": 0
        },
        "9": {
          "name": "Accommodation",
          "icon": "🛌",
          "color": "#5de1a3",
          "id": 9,
          "order": 0
        },
        "10": {
          "name": "Transport",
          "icon": "🚌",
          "color": "#6f2ee1",
          "id": 10,
          "order": 0
        },
        "11": {
          "name": "Sport",
          "icon": "🎾",
          "color": "#69e177",
          "id": 11,
          "order": 0
        }
      },
      "paymentmodes": {
        "1": {
          "name": "Credit card",
          "icon": "💳",
          "color": "#FF7F50",
          "id": 1,
          "order": 0,
          "old_id": "c"
        },
        "2": {
          "name": "Cash",
          "icon": "💵",
          "color": "#556B2F",
          "id": 2,
          "order": 0,
          "old_id": "b"
        },
        "3": {
          "name": "Check",
          "icon": "🎫",
          "color": "#A9A9A9",
          "id": 3,
          "order": 0,
          "old_id": "f"
        },
        "4": {
          "name": "Transfer",
          "icon": "⇄",
          "color": "#00CED1",
          "id": 4,
          "order": 0,
          "old_id": "t"
        },
        "5": {
          "name": "Online service",
          "icon": "🌎",
          "color": "#9932CC",
          "id": 5,
          "order": 0,
          "old_id": "o"
        }
      },
      "deletion_disabled": false,
      "categorysort": "a",
      "paymentmodesort": "a",
      "myaccesslevel": 4
    }
    ```
  </details>

### Set Project Info
* Availability:
* Method: PUT
* Endpoint: `<base_endpoint>`
* Parameters:
  * `name`: New name of the project (**NB**: this doesn't change the project ID used in the API)
  * `contact_email`: New contact email for the project. Must be a valid (non-overloaded) address.
  * `autoexport`: Set the [auto-export policy](#autoexport).
  * `currencyname`: Set the main currency of the project.
  * `deletion_disabled`: Whether the deletion of bills is allowed (ticks or not the box "Disable bills deletion" from the web UI). Values `0`, `false` and an empty value untick the box, everything else ticks the box.
  * `categorysort`: A [valid character](#sort) to set the sort mode for the categories.
  * `paymentmodesort`: A [valid character](#sort) to set the sort mode for the payment modes.
* Return: `"UPDATED"`
* Errors:
  * If the email address is not deemed valid, return is `{"contact_email": ["Invalid email address"]}`
  * If `autoexport` is not valid, return is `{"autoexport": ["Invalid frequency"]}`
  * If the `categorysortorder` is not one of the [valid character](#sort), return is `{"categorysort": ["Invalid sort order"]}`
  * If the `paymentmodesortorder` is not one of the [valid character](#sort), return is `{"paymentmodesort": ["Invalid sort order"]}`
* Example usage:
 ```console
  ~$ curl -s -X PUT \
      -d 'name=My Project New Name&\
        contact_email=contactme@example.org&\
        autoexport=d&\
        currencyname=USD&\
        deletion_disabled=1&\
        categorysort=m&\
        paymentmodesort=u' \
      -u 'johndoe:mypassword' \
      https://mynextcloud.org/index.php/apps/cospend/api-priv/projects/my-first-project
  "UPDATED"
 ```
### Delete project
* Availability: Logged and Anonymous requests (must have the `Admin` [access level](#a-note-about-access-levels))
* Method: DELETE
* Endpoint: `<base_endpoint>`
* Return: `{"message": "DELETED"}`
* Errors:
  * If the ID of the project doesn't exist, returns `{"message": "Not found"}` with code 404
* Example usage:
 ```console
  ~$ curl -s -X DELETE -u 'johndoe:mypassword' https://mynextcloud.org/index.php/apps/cospend/api-priv/projects/my-first-project
  {"message": "DELETED"}
 ```
### Get Members
* Availability: Logged and Anonymous requests
* Method: GET
* Endpoint: `<base_endpoint>/members`
* Parameters:
  * `lastchanged`: An integer, representing a Unix timestamp. Only return users that have deen changed _after_ this moment.
* Return: A list of objects, each containing information about a member (not links). This is the same information as you would get in the `members` section of the [project information](#get-project-info). Specifically, each objects of the list looks like the following:
  * `activated` [boolean]: Whether this user is activated or not.
  * `userId` [string]: The user's nextcloud ID (username).
  * `name` [string]: The user's nextcloud full name.
  * `id` [integer]: The user's cospend ID in this project.
  * `weight` [integer]: The user's [weight](user.md#what-is-a-member-ok_woman-) in this project.
  * `color` [object]: An object containing the RGB components (0ver 255) of the color of the user. Specifically:
    * `r` [integer]: the Red component (0-255),
    * `g` [integer]: the Green component (0-255),
    * `b` [integer]: the Blue component (0-255).
  * `lastchanged` [integer]: Unix timestamp of the last modification of the user.

* Example usage:
 ```console
  ~$ curl -u 'johndoe:mypassword' https://mynextcloud.org/index.php/apps/cospend/api-priv/projects/my-first-project/members
  ```

  <details >
    <summary>Sample answer</summary>

  ```json
  {
    [
      {
        "activated": true,
        "userid": "alicedoe",
        "name": "Alice Doe",
        "id": 2,
        "weight": 1,
        "color": {
          "r": 110,
          "g": 166,
          "b": 143
        },
        "lastchanged": 1678636572
      },
      {
        "activated": true,
        "userid": "johndoe",
        "name": "John Doe",
        "id": 1,
        "weight": 1,
        "color": {
          "r": 0,
          "g": 130,
          "b": 201
        },
        "lastchanged": 1678636568
      }
    ]
  }
  ```
  </details>

### Add Member
This will add a member to the project, to associate to bills. The method differs slightly for logged in and anonymous users. To preserve compatibiltiy with IHateMoney, the anonymous version of this endpoint doesn't allow to link the member to a Nextcloud user, while the logged in endpoint allows it.

Users with anonymous access wishing to add a member and link it to a Nextcloud user should use the [v2 endpoint](#add-member-v2) just below.

* Availability: Logged and Anonymous requests
* Endpoint: `<base_endpoint>/members`
* Method: POST
* Parameters:
  * `name`: Name of the user to add. Mandatory.
  * `weight`: Weight the user should have; a positive number, decimal or integer. Defaults to 1.
  * `active`: Boolean, whether the user is active or not. Defaults to True.
  * `color`: The color of the user. Must start by a pound sign, followed by 3 or 6 hexadecimal characters. Defaults to Null
  * `userid`: **Only for logged in endpoint**, not for anonymous. Nextcloud ID (username) of the user to link to that member.

* Return: ID of the newly created member, as a simple integer.
* Errors:
  * If the weight is not a valid positive number, returns `{"message": "Weight is not a valid decimal value"}`
  * If the color isn't a valid 3 or 6 hexadecimal characters prefixed with a pound sign, returns `{"message": "Invalid color value"}`
  * If the name is already present in the list of members, returns `{"message": "This project already has this member"}`
  * If the `name` field is not specified, returns `{"message": "Name field is required"}`

### Add Member V2
This is a complement to the [previous endpoint](#add-member). This brings to anonymous users the possibility to add a member and link them to a Nextcloud user.
* Availability: Anonymous requests
* Endpoint: `/apiv2/projects/<project_token>/<project_password>/members`
* Method: POST
* Note: This endpoint allows you to link a Nextcloud user as a Cospend member. Not specifying `userid` is strictly the same as the [basic `addmember`](#add-member)
* Parameters:
  * `name`: Name of the user to add. Mandatory.
  * `weight`: Weight the user should have; a positive number, decimal or integer. Defaults to 1.
  * `active`: Boolean, whether the user is active or not. Defaults to True.
  * `color`: The color of the user. Must start by a pound sign, followed by 3 or 6 hexadecimal characters. Defaults to Null
  * `userid`: Nextcloud ID (username)  of the user to link to that member.
* Return: An object describing the user, with the same fields as the parameters, and the additional field `id` containing the id of the newly created member.
### Edit Member
* Availability: Logged and Anonymous users
* Endpoint: `<base_endpoint>/members/<member_id>`
* Method: PUT
* Parameters:
  * `memberid`: ID of the cospend member to edit. Mandatory.
  * `name`: New name of the member.
  * `weight`: New weight of the member.
  * `activated`: Is the member active or not.
  * `color`: New color of the member.
  * `userid`: Nextcloud ID (username) of a user to link to the member.
* Return:
* Errors:
  * If the member name already exists, returns `{"name": "Name already exists"}` with code 403.
  * If the color isn't a valid 3 or 6 hexadecimal characters prefixed with a pound sign, returns `{"color": "Invalid value"}` with code 403.
  * If the name is invalid (contains a forward slash `/`), returns `{"name": "Invalid member name"}` with code 403.
  * If the weight is not a valid positive number, returns `{"weight": "Not a valid decimal value"}` with code 403
  * If the member ID (in the URL) is not a valid existing member, returns `{"name": "This project have no such member"}` with code 403.

### Delete Member
* Availability: Logged and Anonymous requests.
* Endpoint: `<base_endpoint>/members/<member_id>`
* Method: DELETE
* Return: `"OK"`
* Errors:
  * If the `<member_id>` is not found, returns `"Not found"` with code 404.

### Get Bills (logged in)
This endpoint is slightly different whether you're anonymous or logged in, although the endpoint is the same.
* Availability: Logged in requests.
* Endpoint: `<base_endpoint>/bills` (**Only logged in base endpoint**)
* Method: GET
* Parameters:
  * `lastchanged`: An integer, representing a Unix timestamp. The lower limit for bills' `lastchanged` field. Aka, returns all bills that have been last modified after this date. **Note: any data will be accepted**, make sure you pass correct values. Even letters and special characters won't yield an error.
* Return: An **object** (contrary to the anonymous endpoint), with the following entries:
  * `bills` [list]: A list of objects, each representing one bill (one elemenent from the web UI). They are sorted ascendingly by their field `timestamp`. Each bill is composed of the following entries:
    * `id` [integer]: ID of the bill.
    * `amount` [number]: Amount of the bill.
    * `what` [string]: Name/title of the bill.
    * `comment` [string]: Comment of the bill
    * `timestamp` [int]: Unix timestamp of the _creation_ of the bill.
    * `date` [string]: Human-readable date of the _creation_ of the bill, in the format `YYYY-MM-DD`. Time is not present in this field.
    * `payer_id` [int]: ID of the Cospend member that paid the bill.
    * `owers` [list]: List of objects, containing details about people owing money on this bill. Each object contains the following:
      * `id` [int]: Cospend ID of this ower
      * `weight` [number]: Weight of the ower in this particular bill
      * `name` [string]: The ower's Nextcloud's full name.
      * `activated` [boolean]: Whether the ower is active or not.
    * `owerIds` [list]: List of which member is listed as owers in this bill. This is the same information as present in `owers`, but this only contains the list of member ID of the owers, and nothing else.
    * `repeat` [string]: The [repeat cycle](#repeat) of the bill.
    * `paymentmode` [string]: Legacy Payment mode ID, defined by a single character (or `n` if no payment or a non-default payment mode is selected, see [this note](#payment-modes-id)).
    * `paymentmodeid` [integer]: The modern ID of the payment mode used for this bill; 0 means no payment mode has been selected. If the payment mode is one of the default ones, this is redundant to `paymentmode`.
    * `categoryid` [integer]: Similarly represents the ID of the category of the bill. 0 means no category has been selected.
    * `lastchanged` [integer]: Unix timestamp of the moment this bill was last edited/changed.
    * `repeatallactive` [integer]: Whether the bill should repeat for all members currently active or specifically the current owers of the bill. See [this note](#repeat) for how it's used.
    * `repeatuntil` [integer]: Unix timestamp of the datetime after which the bill will stop repeating. `null` if no date has been set.
    * `repeatfreq` [integer]: Modifier of the frequency for repeating. See [this note](#repeat) for what it means.
  * `allbillsid` [list]: A list of all the IDs of existing bills. Even if the bills have been filtered out by the `lastchanged` parameter, their ID will still appear here.
  * `timestamp` [integer]: Unix timestamp of the time of the request

* Example usage:
  ```console
  ~$ curl -s -u 'johndoe:mypassword' https://mynextcloud.org/index.php/apps/cospend/api-priv/projects/my-first-project/bills
  ```

  <details>
    <summary>Sample answer</summary>

    ```json
    {
      "bills": [
        {
          "id": 1,
          "amount": 200,
          "what": "First bill",
          "comment": "",
          "timestamp": 1678636575,
          "date": "2023-03-12",
          "payer_id": 1,
          "owers": [
            {
              "id": 1,
              "weight": 1,
              "name": "John Doe",
              "activated": true
            },
            {
              "id": 2,
              "weight": 1,
              "name": "Alice Doe",
              "activated": true
            }
          ],
          "owerIds": [
            1,
            2
          ],
          "repeat": "n",
          "paymentmode": "n",
          "paymentmodeid": 0,
          "categoryid": 0,
          "lastchanged": 1680875221,
          "repeatallactive": 0,
          "repeatuntil": null,
          "repeatfreq": 1
        },
        {
          "id": 2,
          "amount": 66,
          "what": "Yet another bill",
          "comment": "",
          "timestamp": 1678645901,
          "date": "2023-03-12",
          "payer_id": 1,
          "owers": [
            {
              "id": 1,
              "weight": 1,
              "name": "Olivier",
              "activated": true
            },
            {
              "id": 2,
              "weight": 1,
              "name": "Coralie",
              "activated": true
            }
          ],
          "owerIds": [
            1,
            2
          ],
          "repeat": "n",
          "paymentmode": "n",
          "paymentmodeid": 0,
          "categoryid": 0,
          "lastchanged": 1678645916,
          "repeatallactive": 0,
          "repeatuntil": null,
          "repeatfreq": 1
        }
      ],
      "allBillIds": [
        2,
        1
      ],
      "timestamp": 1680875473
    }
    ```
  </details>


### Get Bills (anonymous)
This endpoint is slightly different whether you're anonymous or logged in, although the endpoint is the same. It allows you to _limit_ the results, but not _filter_ them.
* Availability: Anonymous requests
* Endpoint: `<base_endpoint>/bills` (**Only anonymous base endpoint**)
* Method: GET
* Parameters:
  * `lastchanged`: An integer, representing a Unix timestamp. The lower limit for bills' `lastchanged` field. Aka, returns all bills that have been last modified after this date. **Note: any data will be accepted**, make sure you pass correct values. Even letters and special characters won't yield an error.
  * `limit`: An integer, how may items should be returned at maximum.
  * `offset`: An integer (starting at 0), defining from which position of the list should the limit apply. Ignored if `limit` is not specified. Default to 0 (no element discarded).
  * `reverse`: A boolean, to determine if the list of bills should be sorted ascending (default, false) or descending (true). `0` and `false` mean False, everything else means True.
* Return: A **list** (contrary to the logged in endpoint) of all the bills. This is the same as the list of `bills` returned by the [logged in endpoint](#get-bills-logged-in). This method doesn't return `allBillIds` or `timestamp`, it just returns the list of bills. As such, the parameters passed influence how this list is returned. The parameters are applied in the following order (assuming all of them have been provided):
  1. Get all of the bills with that have been modified after `lastchanged`
  2. Sort the list ascending or descending according to `reverse`
  3. Discard elements before `offset`
  4. Discard elements after `limit`.

  Assuming, without parameters, the bills returned would be `[1,2,3,4,5]`, and the parameters are `reverse=true&offset=2&limit=2`, the end result will be `[3,2].
* Errors:
  * Negative `offset` or `limit` throws an error 500, with message `parse error: Invalid numeric literal at line 1, column 10`.

* Example usage:
  ```console
  $~ curl  -s 'https://mynextcloud.org/index.php/apps/cospend/api/projects/bb9d1bced1d3896e6672db461753e93d/no-pass/bills?reverse=truelimit=2&offset=1'
  ```

  <details>
    <summary>Sample answer</summary>

    ```json
    [
      {
        "id": 5,
        "amount": 351,
        "what": "A nice bill",
        "comment": "",
        "timestamp": 1679234191,
        "date": "2023-03-19",
        "payer_id": 3,
        "owers": [
          {
            "id": 3,
            "weight": 1,
            "name": "John Doe",
            "activated": true
          }
        ],
        "owerIds": [
          3
        ],
        "repeat": "n",
        "paymentmode": "n",
        "paymentmodeid": 0,
        "categoryid": 0,
        "lastchanged": 1679234239,
        "repeatallactive": 0,
        "repeatuntil": null,
        "repeatfreq": 1
      },
      {
        "id": 6,
        "amount": 69,
        "what": "A nice bill",
        "comment": "",
        "timestamp": 1679234191,
        "date": "2023-03-19",
        "payer_id": 3,
        "owers": [
          {
            "id": 4,
            "weight": 1,
            "name": "Alice Doe",
            "activated": true
          }
        ],
        "owerIds": [
          4
        ],
        "repeat": "n",
        "paymentmode": "n",
        "paymentmodeid": 0,
        "categoryid": 0,
        "lastchanged": 1679234239,
        "repeatallactive": 0,
        "repeatuntil": null,
        "repeatfreq": 1
      },
      {
        "id": 3,
        "amount": 100,
        "what": "My first bill",
        "comment": "",
        "timestamp": 1679234084,
        "date": "2023-03-19",
        "payer_id": 3,
        "owers": [
          {
            "id": 3,
            "weight": 1,
            "name": "John Doe",
            "activated": true
          },
          {
            "id": 4,
            "weight": 1,
            "name": "Alice Doe",
            "activated": true
          }
        ],
        "owerIds": [
          3,
          4
        ],
        "repeat": "n",
        "paymentmode": "n",
        "paymentmodeid": 0,
        "categoryid": 0,
        "lastchanged": 1679234133,
        "repeatallactive": 0,
        "repeatuntil": null,
        "repeatfreq": 1
      }
    ]
    ```
  </details>

### Get Bills V2
This is equivalent to [the logged in equivalent](#get-bills-logged-in). Check the other section for detailed specifications.
* Availability: Anonymous requests
* Endpoint: `/apiv2/projects/<project_token>/<project_password>/bills`
* Method: GET

### Get Bills V3
* Availability: Anonymous requests
* Endpoint: `/apiv3/projects/<project_token>/<project_password>/bills`
* Method: GET
* Parameters:
  * `lastchanged`, `offset`, `limit`, `reverse`: the same as [the v2 counterpart](#get-bills-v2)
  * `payerId`: An integer, representing one cospend member's ID. Filter bills to only keep the ones that were paid by this member. It only applies to `payer_id`, not to owerIds.
  * `categoryId`: An integer, representing a category's ID. Filter bills to only keep the ones that have this category indicated.
  * `paymentModeId`: Similarly, an integer representing a payment mode's ID. Filter bills to only keep the ones that have this payment mode indicated. Note, this is the payment mode ID (an integer), not the [legacy ID](#payment-modes-id) (a letter)
  * `includeBillId`: An integer, representing the ID of one (1) bill, to forcefully include despite the limit filtering. This is only available when `limit` is used, otherwise the parameter is ignored. This
  * `searchTerm`:
* Return: An object, containing the following:
  * `nb_bills` [integer]: The number of bills _filtered_, before _limits_. The number here may be different from the actual number of bills in `bills`, if you've used `limit`.
  * `bills` [list]: List of bills, with the same structure of other method ([logged in](#get-bills-logged-in), [anonymous](#get-bills-anonymous), [anonymous v2](#get-bills-v2))
<!-- * Errors:
  * -->
* Example usage:
  ```console
  $~ curl -s 'https://mynextcloud.org/index.php/apps/cospend/apiv3/projects/bb9d1bced1d3896e6672db461753e93d/no-pass/bills?limit=2'
  ```

  <details>
    <summary>Sample answer</summary>

    ```json
    {
      "nb_bills": 3,
      "bills": [
        {
          "id": 3,
          "amount": 100,
          "what": "My first bill",
          "comment": "",
          "timestamp": 1679234084,
          "date": "2023-03-19",
          "payer_id": 3,
          "owers": [
            {
              "id": 3,
              "weight": 1,
              "name": "John Doe",
              "activated": true
            },
            {
              "id": 4,
              "weight": 1,
              "name": "Alice Doe",
              "activated": true
            }
          ],
          "owerIds": [
            3,
            4
          ],
          "repeat": "n",
          "paymentmode": "n",
          "paymentmodeid": 0,
          "categoryid": 0,
          "lastchanged": 1679234133,
          "repeatallactive": 0,
          "repeatuntil": null,
          "repeatfreq": 1
        },
        {
          "id": 5,
          "amount": 351,
          "what": "A nice bill",
          "comment": "",
          "timestamp": 1679234191,
          "date": "2023-03-19",
          "payer_id": 3,
          "owers": [
            {
              "id": 3,
              "weight": 1,
              "name": "John Doe",
              "activated": true
            }
          ],
          "owerIds": [
            3
          ],
          "repeat": "n",
          "paymentmode": "n",
          "paymentmodeid": 0,
          "categoryid": 0,
          "lastchanged": 1679234239,
          "repeatallactive": 0,
          "repeatuntil": null,
          "repeatfreq": 1
        }
      ]
    }
    ```
  </details>

  Some more examples, to understand the difference between _limiting_ and _filtering_

  ```console
  $~ curl -s 'https://mynextcloud.org/index.php/apps/cospend/apiv3/projects/bb9d1bced1d3896e6672db461753e93d/no-pass/bills' \
    | jq -r '.bills[].id' # get the ID of each returned bills, no **filter** or **limit**

  1,2,7,8,10,9,11,12


  $~ curl -s 'https://mynextcloud.org/index.php/apps/cospend/apiv3/projects/bb9d1bced1d3896e6672db461753e93d/no-pass/bills?limit=3' \
    | jq -r '.bills[].id' # **limit** to 3 items

  1,2,7

  $~ curl -s 'https://mynextcloud.org/index.php/apps/cospend/apiv3/projects/bb9d1bced1d3896e6672db461753e93d/no-pass/bills?limit=3&offset=4' \
    | jq -r '.bills[].id' # same **limit** but offset by 4

  10,9,11

  $~ curl -s 'https://mynextcloud.org/index.php/apps/cospend/apiv3/projects/bb9d1bced1d3896e6672db461753e93d/no-pass/bills?payerId=1' \
    | jq -r '.bills[].id' # **filter** only the bills made by a specific member.

  ## all bills but 11 and 12 are paid by member 1.
  1,2,7,8,10,9

  $~ curl -s 'https://mynextcloud.org/index.php/apps/cospend/apiv3/projects/bb9d1bced1d3896e6672db461753e93d/no-pass/bills?limit=3&offset=4&payerId=1' \
    | jq -r '.bills[].id' # merge the two previous: first **filter** to user 1, then **limit** to 3 results, offset by 4

  ## from the offset of 4, the limit is not "reached"
  10,9

  $~ curl -s 'https://mynextcloud.org/index.php/apps/cospend/apiv3/projects/bb9d1bced1d3896e6672db461753e93d/no-pass/bills?limit=3&offset=4&payerId=1' \
    | jq -r '.nb_bills' # same request, but get the field nb_bills instead of listing the bills' ID

  ## despite returning only bills 10 and 9 (after **limit**), nb_bills returns the number of bills after **filter** but excluding **limits**
  6

  $~ curl -s 'https://mynextcloud.org/index.php/apps/cospend/apiv3/projects/bb9d1bced1d3896e6672db461753e93d/no-pass/bills?limit=3&offset=4&payerId=1&includeBillId=10' \
    | jq -r '.bills[].id' # Same, but forcefully include bill 10 (already included)

  10,9

  $~ curl -s 'https://mynextcloud.org/index.php/apps/cospend/apiv3/projects/bb9d1bced1d3896e6672db461753e93d/no-pass/bills?limit=3&offset=4&payerId=1&includeBillId=12' \
    | jq -r '.bills[].id' # Same, but forcefully include bill 12 (not yet included)

  ## includeBillId is a **limiting** option, doesn't take precedence over **filtering**. Bill 12 wouldn't have been returned after filtering, so it's not included after limiting.
  10,9
  ```
### Add Bill
* Availability: Logged in and Anonymous requests
* Method: POST
* Endpoint: `<base_endpoint>/bills`
* Parameters:
  * `date`: A string indicating the date of the payment (optional, but one of `timestamp` and `date` is mandatory)
  * `what`: A string indicating the subject of the bill (optionnal)
  * `payer`: An integer (mandatory)
  * `payed_for`: A string, being a list of coma-separated of integers, each representing a member ID, to indicate the owers of the bill; can be a single integer if there is only one ower.
  * `amount`: A number (integer or floating point) to indicate the value of the bill (mandatory)
  * `repeat`: A string (one character) to indicating the [reapeating mode](#repeat) (optional, `n` by default)
  * `paymentmode`: A string (one character) referring to the `old_id` of a [payment mode](#payment-modes-id) (optional)
  * `paymentmodeid`: An integer representing the `id` of a [payment mode](#payment-modes-id) (optional)
  * `categoryid`: An integer representing the `id` of a category (optional).
  * `repeatallactive`: An integer to indicate if a bill should [repeat](#repeat) for all active members or only the ones indicated (optional, defaults to 0).
  * `repeatuntil`: An integer, representing a Unix timestamp of the moment after which a bill should stop repeating (optional; if a bill is repeating and this is not specified, the bill will repeat infinitely)
  * `repeatfreq`: An integer, represeting the frequency of the [bill repetition](#repeat).
  * `timestamp`: An integer, representing a Unix timestamp of the date and time of the bill; takes precedence over `date` (Optional, but one of `timestamp` and `date` is mandatory),
  * `comment`: A string to indicate a comment to the bill (optionnal)
* Return: A single integer, representing the ID of the bill that has just been added.
* Errors:
  * If neither `timestamp` nor `date` are specified, returns `{"message": "Timestamp (or date) field is required"}` with code 400.
  * If `timestamp` is not specified, `date` is specified and `date` can't be interpreted as a valid date, returns `{"date": "Invalid date"}` with code 400.
  * If the field `amount` is not specified, returns `{"amount": "This field is required"}` with code 400.
  * If the field `payer` is not specified, returns `{"payer": "This field is required"}` with code 400.
  * If the field `payer` is specified but is not a vaid member ID: `{"payer": "Not a valid choice"}` with code 400.
  * If `payed_for` if not specified, or can't be interpreted as a list of numbers, returns `{"payer": "Invalid value"}` with code 400.
  * If *any* of the integers of `payed_for` is not a valid member ID, returns `{"payed_for": "Not a valid choice"}` with code 400.

### Edit Bill
* Availability: Logged in and Anonymous requests
* Method: PUT
* Endpoint: `<base_endpoint>/bills/<bill_id>`
* Parameters: They are the same as [Add Bill](#add-bill); note that the endpoint contains the `bill_id`.
* Return:
  * The ID of the bill edited
* Errors (all return code 400):
  * If the `<bill_id>` from the endpoint doesn't match an existing bill, returns `{"message": "There is no such bill"}`.
  * If the `repeat` character is not [valid](#repeat), returns `{"repeat": "Invalid value"}`
  * If `date` is invalid (and `timestamp` is not specified), returns `{"date": "Invalid value"}`.
  * If the field `payer` is specified but is not a vaid member ID: `{"payer": "Not a valid choice"}`.
  * If *any* of the integers of `payed_for` is not a number, returns `{"payed_for": "Invalid value"}`; if either of them isn't a valid member ID, returns `{"payed_fo": "Not a valid choice"}`.

### Edit Bills
Using anonymous access, it's possible to edit bills in batches.
Instead of specifying the bills as a part of the URL, the bills are passed as a parameter (coma-separated integers).
The other parameters are the same as [Edit Bill](#edit-bill).
The values are applied identically to all of the bills passed.

Note that in case of an error (notably if one of the ID is invalid), all bills before the error are processed, but the ones after are not.
* Availability: Anonymous requests only
* Method: PUT
* Endpoint: `<base_endpoint>/bills`
* Parameters:
  * `bills`: Coma-separated integers, each being a valid existing bill to edit.
  * Same as [Edit Bill](#edit-bill).
* Return: The same coma-separated list of IDs passed as parameter.
* Errors:
  * Same errors than [Edit Bill](#edit-bill).

### Delete Bill
* Availability: Logged in and Anonymous requests.
* Method: DELETE
* Endpoint: `<base_endpoint>/bills/<bill_id`
* Return: `OK`
* Errors:
  * If the ID provided doesn't match a valid bill, returns `{"message": "Not Found"}`, with code 400.

### Delete Bills
Similarly to [Edit Bills](#edit-bills), this is an anonymous-only helper endpoint, to allow to delete bills in batches.
Specify the bills as coma-separated integers, each representing one of the bills to delete.

Note that in case of an error (notably if one of the ID is invalid), all bills before the error are processed, but the ones after are not.

* Availability: Anonymous requests
* Endpoint: `<base_endpoint>/bills`
* Method: DELETE
* Parameters:
  * `bills`: Coma-separated integers, each being a valid existing bill to edit.
* Return: `OK`
* Errors:
  * If *any* of the ID provided doesn't match a valid bill, returns `{"message": "Not Found"}`, with code 400.
### Get Project Statistics
This endpoint will retrieve all the bills that *match all* the filters passed as parameters, and compute statistics on them.

Note: statistics include operations on the numbers; due to their nature, operations on floating points may be slightly erroneous; make you take that into account when displaying your results. Remember that [`0.1 + 0.2 != 0.3`](https://blog.reverberate.org/2016/02/06/floating-point-demystified-part2.html)
* Availability: Logged in and Anonymous requests
* Method: GET
* Endpoint: `<base_endpoint>//statistics`
* Parameters:
  * `tsMin`: An integer, representing a Unix timestamp; compute stats only on bills paid *after* that moment
  * `tsMax`: An integer, representing a Unix timestamp; compute stats only on bills paid *before* that moment
	* `paymentModeId`: An integer, representing a [payment mode's ID](#payment-modes-id); compute stats only on bills with that payment mode ID
  * `categoryId`: An integer, representing a category's ID; compute stats only on bills with that category ID.
  * `amountMin`: A number representing a minimum amount for bills; compute stats only on bills with a *greater* amount than this.
  * `amountMax`: A number representing a minimum amount for bills; compute stats only on bills with a *lower* amount than this.
	* `showDisabled`: A string, to indicate if stats should be computer on all the active members only (default) or all members including the disabled ones. `0` to show all members, anything else to only show active members.
  * `currencyId`: An integer, representing a currency's ID; compute stats only on bills paid with that currency.
  * `payerId`: An integer, representing a member's ID in the project; compute stats only on bills paid by that member.
* Return: an object, with the following fields:
  * `stats`: a list of objects; each entry represents some stats on the members. Sorted aphabetically by their lowercase `name`. Each entry contains the following fields:
    * `balance` [number]: How much that member ows others or is owed by other members, overall, regardless of the filters passed.
    * `filtered_balance` [number]: The balance of the user after applying the filters passed as parameters.
    * `paid` [number]: How much that member paid across the bills (sum of the total of the bills paid by that member).
    * `spent` [number]: How much that member spent across bills (sum of how much that user owes in each bills, including the ones not paid by that user).
    * `member`: An object giving information on the user. The same information as returned in [Get Members](#get-members).
  * `memberMonthlyPaidStats` [object]: An object, grouping how many each member _paid_ on each month. Each entry is a month (`YYYY-MM`), except one special entry that is `Average per month`.
    * For each month (and the special `Average per month`), there is the ID of each member that contributed that month (as a string), and how much they paid over that month.
    * The special "ID" `"0"` is the total for that month (e.g. member `"1"` paid 500, member `"2"` paid 100, then "member" `"0"` paid 1500)
  * `memberMonthlySpentStats` [object]: An object, with the same construction as `memberMonthlyPaidStats` but declines how many each member _spent_ on each month.
  * `categoryStats`: An object OR a list, saying how much has been spent in each category.
    * If no bill has a category, the result is a list containing a single number, representing the total spent (in the "None" category)
    * If at least one bill has a category, the result is an object; keys are the categories' ID, and the values are how much has been spent in that category. Additionally, "category `"0"`" represents the bills without category.
  * `categoryMonthlyStats`: Similarly, an object OR a list, giving how much has been spent monthly for each category.
    * If no bill has a category, the result is a list containing a single object (same format as `memberMonthlyPaidStats`: one key `YYYY-MM` per month plus an additional `"Average per month"`)
    * If at least one bill has a category, the result is an object, which keys are the categories' ID and the values are the monthly stats (as above); the special "category `"0"` represents all the bills without category.
  * `paymentModeStats`: Similarly, an object OR a list, for how much as been spent with each payment mode overall.
  * `paymentModeMonthlyStats`: Similarly, an object OR a list, for how much as been spent during each month with each payment mode.
  * `categoryMemberStats`: Similarly, an object OR a list, for how much each member spent per category. Keys are the categories' ID, values are an object; in this object, keys are the members' ID and values are how much this member spent on this category.
  * `memberIds` [list]: List of integers, representing the IDs of all the _active_ members of the project.
  * `allMemberIds` [list]: List of integers, representing the IDs of all the members of the project (even the disabled ones).
  * `membersPaidFor` [object]: An object representing how much each member paid for other members.
    * Each key is a member ID. Values are object to indicate how much a member (parent) paid for each other member (children), in which the keys are members' ID. There is also a special `"total"` key, representing how much the (parent) member paid in total.
    * There is an additional special key `total`, leading to another object with members ID as keys. Each line represents how much was paid for this user (how much this member spent, this is the same as `stats.spent` above)
  * `realMonths` [list]: List of all the months (still in the format `YYYY-MM`) that the stats cover.
* Example usage:
  ```console
  ## In this, no bill has a category
  ~$ curl  -s 'https://mynextcloud.org/index.php/apps/cospend/api/projects/bb9d1bced1d3896e6672db461753e93d/no-pass/statistics'
  ```
  <details>
    <summary>Sample answer</summary>

  ```json
  {
    "stats": [
      {
        "balance": -1105.6666666667002,
        "filtered_balance": -1105.6666666667002,
        "paid": 1000,
        "spent": 2105.6666666667,
        "member": {
          "activated": true,
          "userid": "alicedoe",
          "name": "Alice Doe",
          "id": 2,
          "weight": 1,
          "color": {
            "r": 110,
            "g": 166,
            "b": 143
          },
          "lastchanged": 1678636572
        }
      },
      {
        "balance": 1105.6666666667002,
        "filtered_balance": 1105.6666666667002,
        "paid": 2600.00000000003,
        "spent": 1494.33333333333,
        "member": {
          "activated": true,
          "userid": "johndoe",
          "name": "John Doe",
          "id": 1,
          "weight": 1,
          "color": {
            "r": 0,
            "g": 130,
            "b": 201
          },
          "lastchanged": 1678636568
        }
      }
    ],
    "memberMonthlyPaidStats": {
      "2023-03": {
        "2": 0,
        "1": 266,
        "0": 266
      },
      "2023-04": {
        "2": 1000,
        "1": 2334.00000000003,
        "0": 3334.00000000003
      },
      "Average per month": {
        "2": 500,
        "1": 1300.000000000015,
        "0": 1800.000000000015
      }
    },
    "memberMonthlySpentStats": {
      "2023-03": {
        "2": 133,
        "1": 133,
        "0": 266
      },
      "2023-04": {
        "2": 1972.6666666667002,
        "1": 1361.33333333333,
        "0": 3334.00000000003
      },
      "Average per month": {
        "2": 1052.83333333335,
        "1": 747.166666666665,
        "0": 1800.000000000015
      }
    },
    "categoryStats": [
      3600.00000000003
    ],
    "categoryMonthlyStats": [
      {
        "2023-03": 266,
        "2023-04": 3334.00000000003,
        "Average per month": 1800.000000000015
      }
    ],
    "paymentModeStats": {
      "0": 2800.00000000003,
      "3": 600,
      "26": 200
    },
    "paymentModeMonthlyStats": {
      "0": {
        "2023-03": 266,
        "2023-04": 2534.00000000003,
        "Average per month": 1400.000000000015
      },
      "3": {
        "2023-04": 600,
        "Average per month": 300
      },
      "26": {
        "2023-04": 200,
        "Average per month": 100
      }
    },
    "categoryMemberStats": [
      {
        "2": 1000,
        "1": 2600.00000000003
      }
    ],
    "memberIds": [
      2,
      1
    ],
    "allMemberIds": [
      2,
      1
    ],
    "membersPaidFor": {
      "2": {
        "2": 550,
        "1": 450,
        "total": 1000
      },
      "total": {
        "2": 2105.6666666667,
        "1": 1494.33333333333
      },
      "1": {
        "2": 1555.6666666667002,
        "1": 1044.33333333333,
        "total": 2600.00000000003
      }
    },
    "realMonths": [
      "2023-03",
      "2023-04"
    ]
  }
  ```
  </details>

  ```console
  ## The same, but >one< bill has a category
  ~$ curl  -s 'https://mynextcloud.org/index.php/apps/cospend/api/projects/bb9d1bced1d3896e6672db461753e93d/no-pass/statistics'
  ```

  <details>
    <summary>Sample answer</summary>

    ```json
    {
    "stats": [
      {
        "balance": -1105.6666666667002,
        "filtered_balance": -1105.6666666667002,
        "paid": 1000,
        "spent": 2105.6666666667,
        "member": {
          "activated": true,
          "userid": "alicedoe",
          "name": "Alice Doe",
          "id": 2,
          "weight": 1,
          "color": {
            "r": 110,
            "g": 166,
            "b": 143
          },
          "lastchanged": 1678636572
        }
      },
      {
        "balance": 1105.6666666667002,
        "filtered_balance": 1105.6666666667002,
        "paid": 2600.00000000003,
        "spent": 1494.33333333333,
        "member": {
          "activated": true,
          "userid": "johndoe",
          "name": "John Doe",
          "id": 1,
          "weight": 1,
          "color": {
            "r": 0,
            "g": 130,
            "b": 201
          },
          "lastchanged": 1678636568
        }
      }
    ],
    "memberMonthlyPaidStats": {
      "2023-03": {
        "2": 0,
        "1": 266,
        "0": 266
      },
      "2023-04": {
        "2": 1000,
        "1": 2334.00000000003,
        "0": 3334.00000000003
      },
      "Average per month": {
        "2": 500,
        "1": 1300.000000000015,
        "0": 1800.000000000015
      }
    },
    "memberMonthlySpentStats": {
      "2023-03": {
        "2": 133,
        "1": 133,
        "0": 266
      },
      "2023-04": {
        "2": 1972.6666666667002,
        "1": 1361.33333333333,
        "0": 3334.00000000003
      },
      "Average per month": {
        "2": 1052.83333333335,
        "1": 747.166666666665,
        "0": 1800.000000000015
      }
    },
    "categoryStats": {
      "0": 2700.00000000003,
      "9": 900
    },
    "categoryMonthlyStats": {
      "0": {
        "2023-03": 266,
        "2023-04": 2434.00000000003,
        "Average per month": 1350.000000000015
      },
      "9": {
        "2023-04": 900,
        "Average per month": 450
      }
    },
    "paymentModeStats": {
      "0": 2800.00000000003,
      "3": 600,
      "26": 200
    },
    "paymentModeMonthlyStats": {
      "0": {
        "2023-03": 266,
        "2023-04": 2534.00000000003,
        "Average per month": 1400.000000000015
      },
      "3": {
        "2023-04": 600,
        "Average per month": 300
      },
      "26": {
        "2023-04": 200,
        "Average per month": 100
      }
    },
    "categoryMemberStats": {
      "0": {
        "2": 100,
        "1": 2600.00000000003
      },
      "9": {
        "2": 900,
        "1": 0
      }
    },
    "memberIds": [
      2,
      1
    ],
    "allMemberIds": [
      2,
      1
    ],
    "membersPaidFor": {
      "2": {
        "2": 550,
        "1": 450,
        "total": 1000
      },
      "total": {
        "2": 2105.6666666667,
        "1": 1494.33333333333
      },
      "1": {
        "2": 1555.6666666667002,
        "1": 1044.33333333333,
        "total": 2600.00000000003
      }
    },
    "realMonths": [
      "2023-03",
      "2023-04"
    ]
  }
  ```
  </details>

### Get Project Settlement
* Availability: Logged in and Anonymous requests
* Method: GET
* Endpoint: `<base_endpoint>/settle`
* Parameters:
  * `centeredOn` [integer]: A member ID. Make the settlement centered on a member, meaning how much that user must receive and give to everyone else to have a balance of 0. Any number is accepted, which will appear like a "virtual" or "transient" member that must simple receive money from people and giving it away to other members.
  * `maxTimestamp` [integer]: Represents a Unix timestamp. Only retrieve bills paid before that moment to compute the balances.
* Return: An object with the keys `transactions` (what transactions must be done to settle), and `balances` (list of total balances for each member).
  * `transactions` [list]: A list of objects. Each item represents one transaction that must be carried, with the keys `from` and `to` (integers, members ID) and the keys `amount` (number, how much must be paid).
  * `balances` [object]: Each key is a member's ID, and the value is the current balance of that member.

* Example usage:
  ```console
    ~$ curl  -s 'https://mynextcloud.org/index.php/apps/cospend/api/projects/bb9d1bced1d3896e6672db461753e93d/no-pass/settle'
  ```
  <details>
    <summary>Sample answer</summary>

    ```json
    {
      "transactions": [
        {
          "to": 1,
          "amount": 1105.6666666667002,
          "from": 2
        }
      ],
      "balances": {
        "2": -1105.6666666667002,
        "1": 1105.6666666667002
      }
    }
    ```
    </details>

  ```console
    ~$ curl  -s 'https://mynextcloud.org/index.php/apps/cospend/api/projects/bb9d1bced1d3896e6672db461753e93d/no-pass/settle?centeredOn=52432335'
  ```
  <details>
    <summary>Sample answer</summary>

    ```json
    {
      "transactions": [
        {
          "from": 2,
          "to": 52432335,
          "amount": 1105.6666666667002
        },
        {
          "from": 52432335,
          "to": 1,
          "amount": 1105.6666666667002
        }
      ],
      "balances": {
        "2": -1105.6666666667002,
        "1": 1105.6666666667002
      }
    }

    ```
    </details>

### Auto Settlement
This function calls the same method as [Get Project Settlement](#get-project-settlement), then actually creates the bills that would have been returned.
If the member of the project decide it's time to settle, they could first call the former endpoint, perform the recommended transcations, then automatically create the bills with this endpoint.

Each bill created with this method will have the tile `<member_from_name> → <member_to_name>` (using the full names).
* Availability: Logged in and Anonymous requests
* Method: GET
* Endpoint: `<base_endpoint>/autosettlement`
* Return: `{"success": true}`
* Errors:
  * If any of the bill has an issue while being created, `{"message": "Error when adding a bill"}` is returned, with code 403. All bills before the creation will be created, the error'ed bill and the following won't.

### Add Currency
* Availability: Logged in and Anonymous requests
* Method: POST
* Endpoint: `<base_endpoint>/currency`
* Parameters:
  * `name`: The name of the currency (mandatory).
  * `currency`: A number representing the exchange rate. 1 of this currency = X of main currency (mandatory).
* Return: A simple integer, the ID of the newly created currency.
* Errors:
  * If you don't have the permissions to manage currencies, `{"message": "You are not allowed to manage currencies"}`, with code 401.

### Edit Currency
* Availability: Logged in and Anonymous requests
* Method: PUT
* Endpoint: `<base_endpoint>/currency/<currency_id>`
* Parameters:
  * `name`: New name of the currency.
  * `rate`: New exchange rate of the currency.
* Return:
* Errors:
  * If you don't have the permissions to manage currencies, `{"message": "You are not allowed to manage currencies"}`, with code 401.
  * If the `<currency_id>` doesn't match an existing currency, `{"message": "This project have no such currency"}`, with code 400.
  * If `name` is empty or if the exchange rate is 0, `{"message": "Incorrect field values"}`, with code 400.

### Delete Currency
* Availability: Logged in and Anonymous requests
* Method: DELETE
* Endpoint: `<base_endpoint>/currency/<currency_id>`
* Return: The ID of the deleted currency (same as passed in the URL)
* Errors:
  * If you don't have the permissions to manage currencies, `{"message": "You are not allowed to manage currencies"}`, with code 401.
  * If the `<currency_id>` doesn't match an existing currency, `{"message": "Not found"}`, with code 400.

### Add Payment Mode
* Availability: Logged in and Anonymous requests
* Method: POST
* Endpoint: `<base_endpoint>/paymentmode`
* Parameters:
  * `name`: Name of the new payment mode (mandatory)
  * `icon`: The icon of the payment mode, as a string (optional).
  * `color`: Color of the payment mode (format `#RRGGBB`). Any text will be accepted and stored as-is, but it will just not be possible to display it, it will default to black on the official Cospend web app (optional).
  * `order`: Which position should the payment mode have when sorting is manual (default 0); **Only available on anonymous endpoint** (optionlal)
* Return: The ID of the newly created payment mode, an integer.
* Example usage:
 ```console
  ~$ curl -s -X POST \
    --data-urlencode 'color=#FF0000' \
    --data-urlencode 'name=MyAPIPaymentMode' \
    --data-urlencode 'icon=💯' \
    -u 'johndoe:mypassword' \
    'https://mynextcloud.org/index.php/apps/cospend/api-priv/projects/my-first-project/paymentmode'
  31
 ```
### Edit Payment Mode
Edit a payment mode. The name is mandatory, while other fields are optional; however non-used fields will delete the information. So you should specify all fields you don't intend to delete, even if it means re-specifying the same fields as when creating.
* Availability: Logged in and Anonymous requests
* Method: PUT
* Endpoint: `<base_endpoint>/paymentmode/<pm_id>`
* Parameters:
  * `name`: Name of the new payment mode (mandatory)
  * `icon`: The icon of the payment mode, as a string (optional).
  * `color`: Color of the icon (format `#RRGGBB`).

* Return: The whole new object. Roughly the same as in [Get project info](#get-project-info), except that `order` is not present, but instead `project_id` is returned.
  * `name` [string]
  * `icon` [string]
  * `color` [string]
  * `id` [integer]
  * `projectid` [string]
  * `old_id` [string]
* Errors:
  * If the `<pm_id>` is not a valid payment mode ID, `{"message": "This project has no such payment mode"}`, with code 400
  * If `name` is not specified, `{"message": "Incorrect field value"}` with code 400.
* Example usage:
 ```console
  ~$ curl -X PUT \
    --data-urlencode 'name=My first edited payment mode'\
    -u 'johndow:mypassword' \
    'https://mynextcloud.org/index.php/apps/cospend/api-priv/projects/my-first-project/paymentmode/32

  {
    "name": "My first edited payment mode",
    "icon": "",
    "color": null,
    "id": 32,
    "projectid": "my-first-project",
    "old_id": null
  }
 ```

### Delete Payment Mode
* Availability: Logged in and Anonymous requests
* Method: DELETE
* Endpoint: `<base_endpoint>/paymentmode/<pm_id>`
* Return: `<pm_id>`, the ID of the deleted payment mode (same as provided), as an integer.
* Errors:
  * If `<pm_id>` doesn't match any existing payment mode, `{"message": "Not found"}` with code 400

### Add Category
* Availability: Logged in and Anonymous requests
* Method: POST
* Endpoint: `<base_endpoint>/category`
* Parameters:
  * `name`: Name of the new category (mandatory)
  * `icon`: The icon of the category, as a string (optional).
  * `color`: Color of the category (format `#RRGGBB`). Any text will be accepted and stored as-is, but it will just not be possible to display it, it will default to black on the official Cospend web app (optional).
  * `order`: Which position should the category have when sorting is manual (default 0); **Only available on anonymous endpoint** (optionlal)

* Return: The ID of the newly created category, an integer.
* Example usage:
 ```console
  ~$ curl -s -X POST \
    --data-urlencode 'color=#DEAA12' \
    --data-urlencode 'name=A Nice Category' \
    --data-urlencode 'icon=💯' \
    -u 'johndoe:mypassword' \
    'https://mynextcloud.org/index.php/apps/cospend/api-priv/projects/my-first-project/category'
  57
 ```

### Edit Category
Edit a payment mode. The name is mandatory, while other fields are optional; however non-used fields will delete the information. So you should specify all fields you don't intend to delete, even if it means re-specifying the same fields as when creating.
* Availability: Logged in and Anonymous requests
* Method: PUT
* Endpoint: `<base_endpoint>/category/<categoryid>`
* Parameters:
  * `name`: Name of the new payment mode (mandatory)
  * `icon`: The icon of the payment mode, as a string (optional).
  * `color`: Color of the icon (format `#RRGGBB`).

* Return: The whole new object. Roughly the same as in [Get project info](#get-project-info), except that `order` is not present, but instead `project_id` is returned.
  * `name` [string]
  * `icon` [string]
  * `color` [string]
  * `id` [integer]
  * `projectid` [string]
  * `old_id` [string]
* Errors:
  * If the `<categoryid>` is not a valid category ID, `{"message": "This project has no such payment mode"}`, with code 400
  * If `name` is not specified, `{"message": "Incorrect field value"}` with code 400.
* Example usage:
 ```console
  ~$ curl -X PUT \
    --data-urlencode 'name=Edited category'\
    --data-urlencode 'color=#aabbcc'\
    -u 'johndoe:mypassword' \
    'https://mynextcloud.org/index.php/apps/cospend/api-priv/projects/my-first-project/category/57'

  {
    "name": "Edited category",
    "icon": "",
    "color": "#aabbcc",
    "id": 57,
    "projectid": "my-first-project"
  }
 ```

### Delete Category
* Availability: Logged in and Anonymous requests
* Method: DELETE
* Endpoint: `<base_endpoint>/category/<categoryid>`
* Return: `<categoryid>`, the ID of the deleted category (same as provided), as an integer.
* Errors:
  * If `<categoryid>` doesn't match any existing category, `{"message": "Not found"}` with code 400
