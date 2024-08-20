# Eve Energy Calculator

If you use Eve Energy and happen to have a smart electricity meter, you can't get accurate cost information from the Eve
app, because it doesn't allow entering rate information granular enough. 

This app can import your exported data and your actual rates, and can give you exact cost breakdown for a particular timeframe.

**This is not a consumer-grade software**:

At this stage you probably have to be a developer to use this. You'll need PHP 8.3 and Docker installed on your machine.

Your mileage will vary, but if you feed this thing valid input, it will give you valid output.

## Installation & usage

1. Check this out on your local machine
1. Run `docker compose up` to start Postgres
1. Run `./bin/console do:sch:up --force` to populate the schema (you only have to do this once)
1. Copy `./import/samples/rates.xlsx` into `./import/rates.xlsx` and update the file with your rates. 
1. Export all your individual Eve Energy usage stats and save them in `./import` as separate Excel files.
   1. See `./import/samples/2024-08-19 23/31/43-Old_Computer_Total_Consumption.xlsx` if you're not sure how the file should look like 
1. Run `./bin/console app:import`. All xlsx files from `./import` will be analysed, imported and moved into `./import/imported/`.
1. Run `./bin/console app:report`

### Notes on `rates.xlsx`

1. I recommend reporting a new rate on a new line every time a bill comes thorugh, even though the rates didn't change
2. Take care to cover all dates you want to report by at least one rate by entering a correct date into `effective_since` and `effective_until` columns
3. If you have multiple rates in a day, just use the same `effective_since` and `effective_until` on multiple rates, but also fill the `time_of_day_start` and `time_of_day_end` times. **This is where the magic happens!**
4. The `rate_per_day` represents the supply charge your distributor is charging you regardless of your usage. If you record multiple rates over the same period, just put the same rate in each row.

## A sample report

```shell
janklan@pb eve % bin/console app:report 2023-07-01 2024-06-30 --no-ansi
 ------------ ---------------- ------- ------------- -------------- --------------- 
  Month        Accessory        Cost    Consumption   Rate per kWh   Rate Name      
 ------------ ---------------- ------- ------------- -------------- ---------------   
  2023-12-01   Computer         0.08    0             0.2262         Off peak       
  2023-12-01   Computer         0.24    0             0.2098         Shoulder       
  2023-12-01   Computer         0.81    1             0.477          Peak           
  2023-12-01   Office Heater    0.03    0             0.2262         Off peak       
  2023-12-01   Office Heater    0.18    0             0.2098         Shoulder       
  2023-12-01   Office Heater    4.09    4             0.477          Peak               
 ------------ ---------------- ------- ------------- -------------- --------------- 

Total consumption: 123
Total cost for consumed electricity: 123.12
Total supply charge: 100
Supply charge adjusted for 15% of business use: 15

 [OK] Total deductible for period between 1 July 2023 and 30 June 2024: 138.12     
```

## Feedback

Send a PR, open an issue or start a discussion if you have any questions.
